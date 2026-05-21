<?php

namespace App\Services\Distributor;

use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generates the .xlsx distributor order export. The ADV_INTER mailbox
 * receives this file as an attachment; an ADV_INTER user later uploads
 * it via the dedicated upload page to fire /sendorder.
 *
 * Requires phpoffice/phpspreadsheet (added to composer.json).
 */
class ExcelOrderExportService
{
    /**
     * @return string Absolute path of the generated file on disk.
     * @throws Exception
     */
    public function generate(Order $order): string
    {
        $order->loadMissing(['user', 'product']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Order');

        $this->writeHeaderBlock($sheet, $order);
        $this->writeLinesTable($sheet, $order, $startRow = 14);

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = sprintf(
            'order_%s_%d.xlsx',
            $order->customer_reference ?: $order->id,
            time()
        );
        $directory = storage_path('app/distributor_orders');
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $order->update(['excel_filename' => $filename]);
        Log::info('ExcelOrderExportService: generated', ['order_id' => $order->id, 'path' => $path]);

        return $path;
    }

    private function writeHeaderBlock($sheet, Order $order): void
    {
        $billing  = $order->billing_address  ?: [];
        $delivery = $order->delivery_address ?: [];

        $sheet->setCellValue('A1', 'DISTRIBUTOR ORDER');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        // Sage address identifiers — required by ADV_INTER to map the Excel
        // back to Sage. `code` is the lireInfoClient shape, `numero` the
        // lireAdressesLivraison one (normalized client-side at submit time).
        $deliveryCode = (string) ($delivery['code'] ?? $delivery['numero'] ?? '');
        $billingCode  = (string) ($billing['code']  ?? $billing['numero']  ?? 'FAC');

        // billing_country_code is stored as an order column; fall back to the
        // address JSON if for some reason the column wasn't set.
        $billingCountry = (string) ($order->billing_country_code
            ?? $billing['codepays']
            ?? $billing['zone']
            ?? '');

        $kv = [
            ['Order number',          $order->id],
            ['Customer reference',    $order->customer_reference],
            ['Customer code',         $order->client_code ?: $order->finalClientCode],
            ['Customer name',         $order->raison_sociale ?: $order->finalClient],
            ['Currency',              $order->currency],
            ['Billing address code',  $billingCode],
            ['Billing country code',  $billingCountry],
            ['Billing address',       $this->formatAddress($billing)],
            ['Delivery address code', $deliveryCode],
            ['Delivery address',      $this->formatAddress($delivery)],
            ['Total HT',              $order->total_ht],
            ['Total TTC',             $order->total_ttc],
            ['Discount',              $order->discount_amount],
        ];
        $row = 3;
        foreach ($kv as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", (string) $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }
    }

    private function writeLinesTable($sheet, Order $order, int $startRow): void
    {
        $headers = ['Reference', 'Designation', 'Sales unit', 'Quantity', 'Gross price', 'Discount 1', 'Discount 2', 'Discount 3', 'Line total'];
        foreach ($headers as $i => $header) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue("{$col}{$startRow}", $header);
            $sheet->getStyle("{$col}{$startRow}")->getFont()->setBold(true);
            $sheet->getStyle("{$col}{$startRow}")
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E0E0E0');
            $sheet->getStyle("{$col}{$startRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $row = $startRow + 1;
        foreach ($order->product as $line) {
            $sheet->setCellValue("A{$row}", $line->reference);
            $sheet->setCellValue("B{$row}", $line->designation);
            $sheet->setCellValue("C{$row}", $line->sales_unit);
            $sheet->setCellValue("D{$row}", $line->cartQuantity);
            $sheet->setCellValue("E{$row}", $line->gross_price);
            $sheet->setCellValue("F{$row}", $line->discount_1);
            $sheet->setCellValue("G{$row}", $line->discount_2);
            $sheet->setCellValue("H{$row}", $line->discount_3);
            $sheet->setCellValue("I{$row}", $line->line_total_ht);
            $row++;
        }

        $lastRow = $row - 1;
        if ($lastRow >= $startRow) {
            $sheet->getStyle("A{$startRow}:I{$lastRow}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
    }

    private function formatAddress(array $addr): string
    {
        if (empty($addr)) return '';
        return trim(implode(', ', array_filter([
            $addr['intitule'] ?? null,
            $addr['adresse1'] ?? null,
            $addr['adresse2'] ?? null,
            $addr['adresse3'] ?? null,
            $addr['codepostal'] ?? null,
            $addr['ville'] ?? null,
            $addr['pays'] ?? null,
        ])));
    }
}
