<?php

namespace App\Http\Controllers;

use App\Constants\OrderExportStatus;
use App\Constants\OrderType;
use App\Mail\AdvInterOrderMailer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Distributor\ExcelOrderExportService;
use App\Services\Middleware\MiddlewareClient;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Distributor-specific order endpoints.
 *
 *  - GET  /api/middleware/product-authorization/{reference}/{billingCountry}
 *  - GET  /api/middleware/default-carrier-code/{clientCode}
 *  - POST /api/distributor/order      (persist order + generate Excel + email ADV_INTER)
 */
class DistributorOrderController extends Controller
{
    use ApiResponser;

    // Note: the property is `middlewareClient`, NOT `middleware`. Lumen's
    // base Controller declares `protected $middleware` (the route
    // middleware list); a child cannot override it with weaker visibility.
    public function __construct(
        private MiddlewareClient $middlewareClient,
        private ExcelOrderExportService $excelService,
    ) {}

    /**
     * Product authorization proxy.
     * Spec §5: returns { success, itemCode, authorization }.
     */
    public function productAuthorization(string $reference, string $billingCountry)
    {
        try {
            $path = sprintf(
                '/api/v1/checkAuthorization/%s/%s',
                rawurlencode($reference),
                rawurlencode($billingCountry),
            );
            $payload = $this->middlewareClient->tryGet($path);
            if (!$payload) {
                return response()->json([
                    'date'          => Carbon::now()->toDateTimeString(),
                    'success'       => false,
                    'itemCode'      => $reference,
                    'authorization' => false,
                    'message'       => 'middleware unreachable',
                ], Response::HTTP_BAD_GATEWAY);
            }
            return response()->json($payload);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Persist the distributor order. Generates CUSORDREF, snapshots
     * billing/delivery addresses, captures line prices, generates the
     * Excel file and emails it to ADV_INTER.
     *
     * Expected request body:
     *   {
     *     deliveryAddress: { code, intitule, adresse1, ..., codepays },
     *     products: [{ reference, designation, salesUnit, quantity,
     *                  grossPrice, discount1, discount2, discount3,
     *                  lineTotalHt, lineTotalTtc }, ...],
     *     totals: { ht, ttc, discount },
     *     carrierCode?: "CHRONOPOST"
     *   }
     */
    /**
     * Distributor's own "pending" orders — those that haven't been forwarded
     * to Sage X3 yet (sage_order_reference IS NULL) and aren't archived.
     *
     * Used by the /profile/shipments page to merge local pending orders into
     * the ERP shipments list so distributors see one unified history.
     */
    public function myPendingOrders()
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }
        $orders = \App\Models\Order::query()
            ->where('user_id', $user->id)
            ->where('order_type', \App\Constants\OrderType::DISTRIBUTOR)
            ->whereNull('archived_at')
            ->where(function ($q) {
                $q->whereNull('sage_order_reference')
                  ->orWhere('sage_order_reference', '');
            })
            ->orderByDesc('created_at')
            ->limit(200)
            ->get([
                'id', 'customer_reference', 'order_type', 'client_code',
                'raison_sociale', 'currency', 'total_ht', 'total_ttc',
                'export_status', 'created_at',
            ]);
        return response()->json(['orders' => $orders]);
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user || !$user->isDistributor()) {
            return $this->errorResponse('Only distributor users can submit this order.', Response::HTTP_FORBIDDEN);
        }

        $delivery  = $request->input('deliveryAddress');
        $products  = $request->input('products', []);
        $totals    = $request->input('totals', []);
        $carrier   = (string) $request->input('carrierCode', '');

        if (!is_array($delivery) || empty($delivery['code'])) {
            return $this->errorResponse('A delivery address must be selected.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!is_array($products) || empty($products)) {
            return $this->errorResponse('Order must contain at least one product.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $billing = $user->sage_facturation_address ?: [];

        try {
            $order = DB::transaction(function () use ($user, $delivery, $billing, $products, $totals, $carrier) {
                $order = Order::create([
                    'user_id'              => $user->id,
                    'order_type'           => OrderType::DISTRIBUTOR,
                    'client_code'          => $user->sage_client_code ?: $user->codeclientGC,
                    'raison_sociale'       => $user->raisonsociale,
                    // Keep legacy columns populated for backwards compat with existing views.
                    'finalClientCode'      => $user->sage_client_code ?: $user->codeclientGC,
                    'finalClient'          => $user->raisonsociale,
                    'shippingAddress'      => (string) ($delivery['code'] ?? ''),
                    'billing_country_code' => $user->billing_country_code,
                    'currency'             => $user->currency ?: 'EUR',
                    'carrier_code'         => $carrier,
                    'billing_address'      => $billing,
                    'delivery_address'     => $delivery,
                    'total_ht'             => $totals['ht']       ?? null,
                    'total_ttc'            => $totals['ttc']      ?? null,
                    'discount_amount'      => $totals['discount'] ?? null,
                    'export_status'        => OrderExportStatus::PENDING,
                ]);

                foreach ($products as $p) {
                    Product::create([
                        'order_id'       => $order->id,
                        'reference'      => $p['reference'] ?? '',
                        'designation'    => $p['designation'] ?? null,
                        'sales_unit'     => $p['salesUnit'] ?? 'UN',
                        'cartQuantity'   => (int) ($p['quantity'] ?? 0),
                        'lot'            => $p['lot'] ?? null,
                        'comment'        => $p['comment'] ?? null,
                        'gross_price'    => $p['grossPrice'] ?? null,
                        'discount_1'     => $p['discount1'] ?? null,
                        'discount_2'     => $p['discount2'] ?? null,
                        'discount_3'     => $p['discount3'] ?? null,
                        'line_total_ht'  => $p['lineTotalHt'] ?? null,
                        'line_total_ttc' => $p['lineTotalTtc'] ?? null,
                    ]);
                }

                $order->customer_reference = $order->generateCustomerReference();
                $order->save();
                return $order;
            });

            // Excel + email — failures are non-fatal: order remains persisted with PENDING status.
            try {
                $excelPath = $this->excelService->generate($order->fresh('product'));
                $recipient = config('x3.adv_inter_email');
                if ($recipient) {
                    Mail::to($recipient)->send(new AdvInterOrderMailer($order, $excelPath));
                }
                $order->update([
                    'export_status' => OrderExportStatus::EXPORTED,
                    'exported_at'   => Carbon::now(),
                ]);
            } catch (Exception $e) {
                Log::error('DistributorOrderController: excel/email failed', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
                $order->update(['export_error' => substr($e->getMessage(), 0, 1000)]);
            }

            return response()->json([
                'success' => 1,
                'order'   => $order->fresh('product'),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse('Order creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
