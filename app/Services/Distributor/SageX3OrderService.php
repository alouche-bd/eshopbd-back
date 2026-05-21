<?php

namespace App\Services\Distributor;

use App\Constants\OrderExportStatus;
use App\Models\Order;
use App\Services\Middleware\MiddlewareClient;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Builds the ZSOH payload via ZsohPayloadBuilder and posts it to the
 * middleware /sendorder route. Updates the Order's export_status,
 * sage_order_reference and timestamps based on the response.
 */
class SageX3OrderService
{
    public function __construct(
        private MiddlewareClient $middleware,
        private ZsohPayloadBuilder $builder,
    ) {}

    /**
     * Send the ZSOH payload to Sage X3 via the middleware's generic SOAP
     * wrapper (useX3GenericWSSoap). Body shape matches the e-dental-nest
     * reference implementation:
     *
     *   POST {middleware_base}/v2/useX3GenericWSSoap
     *   { base: "BDRCT|BIOTECH", action: "ZSOH", data: "E;…|L;…|END" }
     *
     * @return array{success:bool, response:array, order:Order}
     */
    public function send(Order $order): array
    {
        $body = $this->builder->build($order);
        $path = config('x3.sendorder_path', '/v2/useX3GenericWSSoap');

        try {
            $response = $this->middleware->post($path, $body);
        } catch (Exception $e) {
            Log::error('SageX3OrderService: middleware useX3GenericWSSoap failed', [
                'order_id' => $order->id,
                'path'     => $path,
                'error'    => $e->getMessage(),
            ]);
            $order->update([
                'export_status' => OrderExportStatus::FAILED,
                'export_error'  => substr($e->getMessage(), 0, 1000),
            ]);
            return ['success' => false, 'response' => ['error' => $e->getMessage()], 'order' => $order->fresh()];
        }

        // useX3GenericWSSoap response shape (per Sage):
        //   { success: true|false, reponse: [{ type, message: "|Création de BIOCO260500008" }, ...] }
        $success  = (bool) ($response['success'] ?? $response['ok'] ?? false);
        $orderRef = $this->extractOrderReference($response);
        $errorMsg = $success ? null : $this->extractErrorMessage($response);

        $order->update([
            'export_status'        => $success ? OrderExportStatus::SENT : OrderExportStatus::FAILED,
            'sent_at'              => $success ? Carbon::now() : null,
            'sage_order_reference' => $success ? ($orderRef ?: null) : null,
            'export_error'         => $success ? null : ($errorMsg ?: 'Sage X3 returned an unspecified error'),
        ]);

        return ['success' => $success, 'response' => $response, 'order' => $order->fresh()];
    }

    /**
     * Parse the Sage "Création de BIOCO260500008" success message out of the
     * `reponse` array. Returns the bare reference when found.
     *
     * The message comes back like: "|Création de BIOCO260500008"
     * The regex picks the first uppercase-letter+digit token in any entry.
     */
    private function extractOrderReference(array $response): string
    {
        $candidates = $response['reponse'] ?? $response['response'] ?? [];
        foreach ((array) $candidates as $entry) {
            $msg = is_array($entry) ? ($entry['message'] ?? '') : (string) $entry;
            if (preg_match('/([A-Z]{2,}[0-9]{4,})/', $msg, $m)) {
                return $m[1];
            }
        }
        return (string) ($response['orderReference'] ?? '');
    }

    /**
     * Collect non-empty error messages from the Sage `reponse` array.
     * Filters out whitespace-padding entries the SOAP wrapper inserts.
     * Falls back to the top-level `message` field, then a generic string.
     */
    private function extractErrorMessage(array $response): string
    {
        $candidates = $response['reponse'] ?? $response['response'] ?? [];
        $messages = [];
        foreach ((array) $candidates as $entry) {
            $msg = is_array($entry) ? (string) ($entry['message'] ?? '') : (string) $entry;
            $clean = trim($msg, " \t\n\r\0\x0B|");
            if ($clean === '') continue;
            $messages[] = $clean;
        }
        if (!empty($messages)) {
            return implode(' · ', $messages);
        }
        if (!empty($response['message'])) {
            return (string) $response['message'];
        }
        return '';
    }

    /**
     * Generate the payload without sending — used by the ADV_INTER preview screen.
     */
    public function preview(Order $order): array
    {
        return $this->builder->build($order);
    }
}
