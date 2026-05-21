<?php

namespace App\Services\Distributor;

use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;

/**
 * Builds the ZSOH payload for Sage X3 from a persisted Order.
 *
 * Spec contract (§13):
 *   Header: E;SALFCY;SOHTYP;SOHNUM;BPCORD;ORDDAT;CUSORDREF;STOFCY;CUR;PJT;*71;*72;*81;*82
 *   Line:   L;ITMREF;ITMDES;SAU;QTY;GROPRI;DISCRGVAL1;DISCRGVAL2;DISCRGVAL3;*91;*92
 *   End:    END
 *
 * Output shape matches the /sendorder middleware contract:
 *   { model: "ZSOH", payload: string[] }
 */
class ZsohPayloadBuilder
{
    /**
     * Build the body to POST to the Sage X3 generic SOAP middleware.
     *
     * Returns the shape expected by useX3GenericWSSoap:
     *   { base: "...", action: "ZSOH", data: "E;...|L;...|END" }
     *
     * Lines are joined with '|' so the entire ZSOH payload fits in a
     * single string — matches the e-dental-nest implementation.
     *
     * @return array{base:string, action:string, data:string}
     */
    public function build(Order $order): array
    {
        $order->loadMissing('product');

        $rows = [$this->header($order)];
        foreach ($order->product as $line) {
            $rows[] = $this->line($line);
        }
        $rows[] = 'END';

        return [
            'base'   => config('x3.base'),
            'action' => config('x3.action', 'ZSOH'),
            'data'   => implode('|', $rows),
        ];
    }

    /**
     * Header: E;SALFCY;SOHTYP;SOHNUM;BPCORD;ORDDAT;CUSORDREF;STOFCY;CUR;PJT;*71;*72;*81;*82
     */
    private function header(Order $order): string
    {
        $site      = config('x3.site', 'CRAP');
        $orderType = config('x3.order_type_code', 'SON');
        $client    = $order->client_code ?: $order->finalClientCode;
        $date      = optional($order->created_at ?: Carbon::now())->format('Ymd');
        $cusOrdRef = $order->customer_reference ?: $order->generateCustomerReference();
        $currency  = $order->currency ?: 'EUR';

        return $this->buildSegment('E', [
            $site,        // SALFCY
            $orderType,   // SOHTYP
            '',           // SOHNUM (empty — Sage allocates)
            $client,      // BPCORD
            $date,        // ORDDAT (YYYYMMDD)
            $cusOrdRef,   // CUSORDREF
            $site,        // STOFCY
            $currency,    // CUR
            '',           // PJT
            '',           // *71 header text
            '',           // *72 header text
            '',           // *81 footer text
            '',           // *82 footer text
        ]);
    }

    /**
     * Line: L;ITMREF;ITMDES;SAU;QTY;GROPRI;DISCRGVAL1;DISCRGVAL2;DISCRGVAL3;*91;*92
     */
    private function line(Product $line): string
    {
        return $this->buildSegment('L', [
            $line->reference,                                // ITMREF
            $line->designation ?? '',                        // ITMDES
            $line->sales_unit ?? 'UN',                       // SAU
            $line->cartQuantity,                             // QTY
            $this->money($line->gross_price),                // GROPRI
            $this->money($line->discount_1),                 // DISCRGVAL1
            $this->money($line->discount_2),                 // DISCRGVAL2
            $this->money($line->discount_3),                 // DISCRGVAL3
            '',                                              // *91
            '',                                              // *92
        ]);
    }

    /**
     * Build a single segment line. Fields are joined by ';'. Each field is
     * sanitized — semicolons and pipes are stripped (the spec uses ';' as a
     * field separator and any literal ';' inside a field would corrupt parsing).
     */
    private function buildSegment(string $code, array $values): string
    {
        $parts = [$code];
        foreach ($values as $v) {
            $parts[] = $this->sanitize($v);
        }
        return implode(';', $parts);
    }

    private function sanitize($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $str = is_numeric($value) ? (string) $value : (string) $value;
        $str = str_replace([';', '|', "\r", "\n"], ' ', $str);
        return trim(preg_replace('/\s+/', ' ', $str));
    }

    private function money($value): string
    {
        if ($value === null || $value === '' || $value === false) {
            return '0';
        }
        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
        }
        return (string) $value;
    }
}
