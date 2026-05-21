<?php

namespace App\Http\Controllers;

use App\Constants\UserType;
use App\Models\Order;
use App\Services\Distributor\SageX3OrderService;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * ADV_INTER workflow endpoints.
 *
 *  - GET  /api/adv-inter/orders                    list orders awaiting forward
 *  - GET  /api/adv-inter/orders/{id}/preview-zsoh  preview the ZSOH payload
 *  - POST /api/adv-inter/orders/{id}/send          send the ZSOH payload to Sage
 *  - POST /api/adv-inter/upload                    parse an uploaded Excel and
 *                                                  return parsed rows (the
 *                                                  ADV_INTER user reviews then
 *                                                  hits /send by orderId)
 */
class AdvInterController extends Controller
{
    use ApiResponser;

    public function __construct(private SageX3OrderService $x3) {}

    private function guardRole()
    {
        $user = Auth::user();
        if (!$user || $user->user_type !== UserType::ADV_INTER) {
            return $this->errorResponse('Forbidden — ADV_INTER role required.', Response::HTTP_FORBIDDEN);
        }
        return null;
    }

    /**
     * Paginated, searchable, sortable, status-filterable list of distributor
     * orders. Query parameters:
     *
     *   search      — substring matched against customer_reference,
     *                 client_code, raison_sociale, billing_country_code
     *   status      — `active` (default — every status EXCEPT sent),
     *                 `pending`, `exported`, `sent`, `failed`,
     *                 or `all` to surface every order regardless of status
     *   sort        — column name (created_at | id | customer_reference |
     *                 raison_sociale | billing_country_code | export_status)
     *   direction   — asc | desc (default desc)
     *   page        — 1-based page number (default 1)
     *   per_page    — items per page (default 25, max 100)
     */
    public function index(Request $request)
    {
        if ($block = $this->guardRole()) return $block;

        $allowedSort = ['created_at', 'id', 'customer_reference', 'raison_sociale', 'billing_country_code', 'export_status'];
        $sort      = in_array($request->input('sort'), $allowedSort, true) ? $request->input('sort') : 'created_at';
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage   = min(100, max(5, (int) $request->input('per_page', 25)));
        $status    = $request->input('status', 'active');
        $search    = trim((string) $request->input('search', ''));

        $query = Order::distributor()->with('product');

        // Status filter.
        //   "active" (default) — open orders: not sent AND not archived
        //   "archived"         — only archived orders
        //   "all"              — surface every status, even sent / archived
        //   anything else      — exact match on export_status (still excludes archived)
        if ($status === 'active') {
            $query->where('export_status', '!=', \App\Constants\OrderExportStatus::SENT)
                  ->whereNull('archived_at');
        } elseif ($status === 'archived') {
            $query->whereNotNull('archived_at');
        } elseif ($status && $status !== 'all') {
            $query->where('export_status', $status)
                  ->whereNull('archived_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('customer_reference', 'like', $like)
                  ->orWhere('client_code', 'like', $like)
                  ->orWhere('raison_sociale', 'like', $like)
                  ->orWhere('billing_country_code', 'like', $like)
                  ->orWhere('id', $search);   // exact match on id
            });
        }

        $paginator = $query->orderBy($sort, $direction)->paginate($perPage);

        return response()->json([
            'orders' => $paginator->items(),
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
            'filters' => compact('search', 'status', 'sort', 'direction'),
        ]);
    }

    /**
     * Downloadable blank Excel template — ADV_INTER users fill it in
     * manually and re-upload via the /upload endpoint.
     *
     * Header block keys must mirror the keys ExcelOrderExportService writes
     * so the parser in AdvInterController::upload() can match a row to an
     * existing order via `Customer reference`.
     */
    public function template()
    {
        if ($block = $this->guardRole()) return $block;

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Order');

        $sheet->setCellValue('A1', 'DISTRIBUTOR ORDER — TEMPLATE');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Replace the example values with the actual order data, then re-upload via the ADV International dashboard.');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

        // Header block with example placeholder values. Order matches what
        // ExcelOrderExportService writes so the upload parser can round-trip.
        $headerKv = [
            ['Order number',          ''],
            ['Customer reference',    'ESHOP-1234-SF000XXXX'],
            ['Customer code',         'SF000XXXX'],
            ['Customer name',         'Distributor company name'],
            ['Currency',              'EUR'],
            ['Billing address code',  'FAC'],
            ['Billing country code',  'BE'],
            ['Billing address',       'Street, ZIP City, Country'],
            ['Delivery address code', 'L0'],
            ['Delivery address',      'Street, ZIP City, Country'],
            ['Total HT',              '0.00'],
            ['Total TTC',             '0.00'],
            ['Discount',              '0.00'],
        ];
        $row = 4;
        foreach ($headerKv as [$label, $sample]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$row}", $sample);
            $sheet->getStyle("B{$row}")->getFont()->setItalic(true)->getColor()->setRGB('888888');
            $row++;
        }

        $lineHeaderRow = $row + 1;
        $columns = ['Reference', 'Designation', 'Sales unit', 'Quantity', 'Gross price', 'Discount 1', 'Discount 2', 'Discount 3', 'Line total'];
        foreach ($columns as $i => $col) {
            $cell = chr(ord('A') + $i) . $lineHeaderRow;
            $sheet->setCellValue($cell, $col);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E0E0E0');
        }

        // Example product rows — same italic+grey treatment so they're
        // visibly placeholders.
        $sampleLines = [
            ['ERP-1', 'Example product 1', 'UN', 2, 12.50, 0,    0, 0, 25.00],
            ['ERP-2', 'Example product 2', 'UN', 3,  7.00, 0.50, 0, 0, 19.50],
            ['ERP-3', 'Example product 3', 'UN', 1, 99.00, 0,    0, 0, 99.00],
        ];
        $sampleStart = $lineHeaderRow + 1;
        foreach ($sampleLines as $rowIndex => $line) {
            $r = $sampleStart + $rowIndex;
            foreach ($line as $i => $val) {
                $cell = chr(ord('A') + $i) . $r;
                $sheet->setCellValue($cell, $val);
                $sheet->getStyle($cell)->getFont()->setItalic(true)->getColor()->setRGB('888888');
            }
        }

        // A handful of blank rows after the examples to invite real data.
        $blankStart = $sampleStart + count($sampleLines);
        for ($i = 0; $i < 7; $i++) {
            $sheet->setCellValue('A' . ($blankStart + $i), '');
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $directory = storage_path('app/distributor_orders');
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
        $filename = 'distributor_order_template.xlsx';
        $path     = $directory . DIRECTORY_SEPARATOR . $filename;

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Single order detail — used by the ADV_INTER editor.
     */
    public function show(int $id)
    {
        if ($block = $this->guardRole()) return $block;
        $order = Order::with('product')->findOrFail($id);
        return response()->json(['order' => $order]);
    }

    /**
     * Create a distributor order from parsed Excel data. Used by the
     * upload flow when the uploaded file has no matching order in the DB.
     *
     * Expected request body shape (mirrors the structure returned by
     * AdvInterController::upload()):
     *   {
     *     parsedHeader: {
     *       "Customer reference": "...", "Customer code": "...",
     *       "Customer name": "...", "Currency": "EUR",
     *       "Delivery address code": "L0", ...
     *     },
     *     parsedLines: [
     *       {reference, designation, salesUnit, quantity, grossPrice, ...}
     *     ]
     *   }
     */
    public function store(Request $request)
    {
        if ($block = $this->guardRole()) return $block;

        $header = (array) $request->input('parsedHeader', []);
        $lines  = (array) $request->input('parsedLines', []);

        if (empty($lines)) {
            return $this->errorResponse('No lines provided.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Recover authenticated user (ADV_INTER) — order is attached to them
        // so the existing user_id constraint is satisfied. The customer fields
        // come from the Excel header.
        $advUser = \Illuminate\Support\Facades\Auth::user();

        try {
            $order = \Illuminate\Support\Facades\DB::transaction(function () use ($header, $lines, $advUser) {
                $clientCode = (string) ($header['Customer code'] ?? '');
                $order = Order::create([
                    'user_id'              => $advUser->id,
                    'order_type'           => \App\Constants\OrderType::DISTRIBUTOR,
                    'customer_reference'   => $header['Customer reference'] ?? null,
                    'client_code'          => $clientCode,
                    'raison_sociale'       => $header['Customer name'] ?? null,
                    'finalClientCode'      => $clientCode,
                    'finalClient'          => $header['Customer name'] ?? '',
                    'shippingAddress'      => (string) ($header['Delivery address code'] ?? ''),
                    'currency'             => $header['Currency'] ?? 'EUR',
                    'billing_country_code' => strtoupper(substr((string) ($header['Billing country code'] ?? ''), 0, 2)) ?: null,
                    'billing_address'      => $this->buildBillingAddressFromHeader($header),
                    'delivery_address'     => $this->buildDeliveryAddressFromHeader($header),
                    'total_ht'             => $this->parseAmount($header['Total HT'] ?? null),
                    'total_ttc'            => $this->parseAmount($header['Total TTC'] ?? null),
                    'discount_amount'      => $this->parseAmount($header['Discount'] ?? null),
                    'export_status'        => \App\Constants\OrderExportStatus::EXPORTED,
                    'exported_at'          => \Illuminate\Support\Carbon::now(),
                ]);

                // If the Excel didn't include a Customer reference, generate
                // one from the new order id like the regular distributor flow.
                if (!$order->customer_reference) {
                    $order->customer_reference = $order->generateCustomerReference();
                    $order->save();
                }

                foreach ($lines as $line) {
                    if (empty($line['reference'])) continue;
                    \App\Models\Product::create([
                        'order_id'       => $order->id,
                        'reference'      => $line['reference'] ?? '',
                        'designation'    => $line['designation'] ?? null,
                        'sales_unit'     => $line['salesUnit'] ?? 'UN',
                        'cartQuantity'   => (int) ($line['quantity'] ?? 0),
                        'gross_price'    => $this->parseAmount($line['grossPrice'] ?? null),
                        'discount_1'     => $this->parseAmount($line['discount1'] ?? null),
                        'discount_2'     => $this->parseAmount($line['discount2'] ?? null),
                        'discount_3'     => $this->parseAmount($line['discount3'] ?? null),
                        'line_total_ht'  => $this->parseAmount($line['lineTotal'] ?? null),
                    ]);
                }

                return $order;
            });

            return response()->json([
                'success' => true,
                'order'   => $order->fresh('product'),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse('Order creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Excel addresses come in as a freeform "Street, ZIP City, Country" line.
     * We persist them in the same JSON shape downstream code (ZSOH builder,
     * Excel re-export, mailers) expects — code identifier + adresse1 holding
     * the raw line + codepays where available.
     */
    private function buildDeliveryAddressFromHeader(array $header): array
    {
        return [
            'code'       => (string) ($header['Delivery address code'] ?? ''),
            'intitule'   => '',
            'adresse1'   => (string) ($header['Delivery address'] ?? ''),
            'adresse2'   => '',
            'adresse3'   => '',
            'codepostal' => '',
            'ville'      => '',
            'pays'       => '',
            'codepays'   => strtoupper(substr((string) ($header['Billing country code'] ?? ''), 0, 2)),
        ];
    }

    private function buildBillingAddressFromHeader(array $header): array
    {
        return [
            'code'       => (string) ($header['Billing address code'] ?? 'FAC'),
            'intitule'   => (string) ($header['Customer name'] ?? ''),
            'adresse1'   => (string) ($header['Billing address'] ?? ''),
            'adresse2'   => '',
            'adresse3'   => '',
            'codepostal' => '',
            'ville'      => '',
            'pays'       => '',
            'codepays'   => strtoupper(substr((string) ($header['Billing country code'] ?? ''), 0, 2)),
        ];
    }

    private function parseAmount($v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (float) $v;
        $clean = preg_replace('/[^0-9\.\-]/', '', (string) $v);
        return $clean === '' ? null : (float) $clean;
    }

    /**
     * Update the editable fields of a distributor order.
     *
     * ADV_INTER staff can amend header data (customer reference, raison
     * sociale, currency, delivery address) and lines (designation, qty,
     * prices, discounts) before forwarding to Sage. The product lines are
     * fully replaced from the request payload — safer than diffing because
     * ADV may have added/removed entries during their review.
     *
     * Orders that have already been sent (export_status === SENT) are
     * locked to avoid double-sending modifications.
     */
    public function update(Request $request, int $id)
    {
        if ($block = $this->guardRole()) return $block;

        /** @var Order $order */
        $order = Order::with('product')->findOrFail($id);

        if ($order->export_status === \App\Constants\OrderExportStatus::SENT) {
            return $this->errorResponse('Order already sent to Sage — cannot be modified.', Response::HTTP_CONFLICT);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $order) {
                $order->update(array_filter([
                    'customer_reference' => $request->input('customer_reference'),
                    'raison_sociale'     => $request->input('raison_sociale'),
                    'client_code'        => $request->input('client_code'),
                    'currency'           => $request->input('currency'),
                    'delivery_address'   => $request->input('delivery_address'),
                    'billing_address'    => $request->input('billing_address'),
                    'total_ht'           => $request->input('total_ht'),
                    'total_ttc'          => $request->input('total_ttc'),
                    'discount_amount'    => $request->input('discount_amount'),
                ], fn ($v) => $v !== null));

                if (is_array($request->input('products'))) {
                    $order->product()->delete();
                    foreach ($request->input('products') as $p) {
                        \App\Models\Product::create([
                            'order_id'       => $order->id,
                            'reference'      => $p['reference'] ?? '',
                            'designation'    => $p['designation'] ?? null,
                            'sales_unit'     => $p['sales_unit'] ?? $p['salesUnit'] ?? 'UN',
                            'cartQuantity'   => (int) ($p['cartQuantity'] ?? $p['quantity'] ?? 0),
                            'lot'            => $p['lot'] ?? null,
                            'comment'        => $p['comment'] ?? null,
                            'gross_price'    => $p['gross_price']    ?? $p['grossPrice']  ?? null,
                            'discount_1'     => $p['discount_1']     ?? $p['discount1']   ?? null,
                            'discount_2'     => $p['discount_2']     ?? $p['discount2']   ?? null,
                            'discount_3'     => $p['discount_3']     ?? $p['discount3']   ?? null,
                            'line_total_ht'  => $p['line_total_ht']  ?? $p['lineTotalHt'] ?? null,
                            'line_total_ttc' => $p['line_total_ ttc'] ?? $p['lineTotalTtc'] ?? null,
                        ]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'order'   => $order->fresh('product'),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse('Update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function previewZsoh(int $id)
    {
        if ($block = $this->guardRole()) return $block;
        $order = Order::with('product')->findOrFail($id);
        return response()->json($this->x3->preview($order));
    }

    public function send(int $id)
    {
        if ($block = $this->guardRole()) return $block;
        $order = Order::with('product')->findOrFail($id);
        $result = $this->x3->send($order);
        return response()->json($result, $result['success'] ? 200 : 502);
    }

    /**
     * Park an order without forwarding it to Sage. Sets archived_at=now()
     * so the order is hidden from "À traiter" but still queryable from the
     * "Archivées" filter.
     */
    public function archive(int $id)
    {
        if ($block = $this->guardRole()) return $block;
        $order = Order::findOrFail($id);
        $order->update(['archived_at' => \Illuminate\Support\Carbon::now()]);
        return response()->json(['success' => true, 'order' => $order->fresh()]);
    }

    /**
     * Move an order back from "Archivées" to "À traiter".
     */
    public function unarchive(int $id)
    {
        if ($block = $this->guardRole()) return $block;
        $order = Order::findOrFail($id);
        $order->update(['archived_at' => null]);
        return response()->json(['success' => true, 'order' => $order->fresh()]);
    }

    /**
     * Duplicate an order — useful when ADV needs to resend a near-identical
     * order or use a previous one as a template. The duplicate gets:
     *   - a fresh id (DB-assigned)
     *   - a fresh customer_reference: ESHOP-{newId}-{clientCode}
     *   - created_at set to NOW
     *   - export_status reset to PENDING (must re-run the workflow)
     *   - no archived_at, no sent_at, no sage_order_reference
     *   - all product lines copied with their current pricing/discounts
     */
    public function duplicate(int $id)
    {
        if ($block = $this->guardRole()) return $block;

        $source = Order::with('product')->findOrFail($id);

        try {
            $copy = \Illuminate\Support\Facades\DB::transaction(function () use ($source) {
                $copy = $source->replicate([
                    'customer_reference', 'export_status', 'exported_at', 'sent_at',
                    'sage_order_reference', 'excel_filename', 'export_error', 'archived_at',
                ]);
                $copy->export_status = \App\Constants\OrderExportStatus::PENDING;
                $copy->created_at = \Illuminate\Support\Carbon::now();
                $copy->updated_at = \Illuminate\Support\Carbon::now();
                $copy->save();

                $copy->customer_reference = $copy->generateCustomerReference();
                $copy->save();

                foreach ($source->product as $line) {
                    $newLine = $line->replicate(['order_id']);
                    $newLine->order_id = $copy->id;
                    $newLine->save();
                }

                return $copy;
            });

            return response()->json([
                'success' => true,
                'order'   => $copy->fresh('product'),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse('Duplicate failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Parse an uploaded Excel file. Returns the rows as JSON so the ADV_INTER
     * UI can show a preview. The actual Sage send happens via /send/{id}
     * — uploading only re-syncs an existing order's lines, never creates one.
     */
    public function upload(Request $request)
    {
        if ($block = $this->guardRole()) return $block;
        if (!$request->hasFile('file')) {
            return $this->errorResponse('No file uploaded.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $tmp = $request->file('file')->getRealPath();
            $spreadsheet = IOFactory::load($tmp);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);

            $header = [];
            $lines  = [];
            $captureLines = false;

            foreach ($rows as $row) {
                $first = trim((string) ($row[0] ?? ''));
                if ($first === '' && !$captureLines) continue;

                // The Excel layout (see ExcelOrderExportService) has a header
                // block of label/value pairs followed by a line-items table.
                if ($first === 'Reference') {
                    $captureLines = true;
                    continue;
                }
                if (!$captureLines) {
                    $header[$first] = trim((string) ($row[1] ?? ''));
                    continue;
                }
                if ($first === '') continue;
                $lines[] = [
                    'reference'   => $first,
                    'designation' => $row[1] ?? null,
                    'salesUnit'   => $row[2] ?? null,
                    'quantity'    => $row[3] ?? null,
                    'grossPrice'  => $row[4] ?? null,
                    'discount1'   => $row[5] ?? null,
                    'discount2'   => $row[6] ?? null,
                    'discount3'   => $row[7] ?? null,
                    'lineTotal'   => $row[8] ?? null,
                ];
            }

            $customerReference = $header['Customer reference'] ?? null;
            $order = $customerReference
                ? Order::where('customer_reference', $customerReference)->with('product')->first()
                : null;

            return response()->json([
                'success'           => true,
                'parsedHeader'      => $header,
                'parsedLines'       => $lines,
                'matchedOrder'      => $order,
                'zsohPreview'       => $order ? $this->x3->preview($order) : null,
            ]);
        } catch (Exception $e) {
            Log::error('AdvInterController::upload failed', ['error' => $e->getMessage()]);
            return $this->errorResponse('Excel parsing failed: ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
