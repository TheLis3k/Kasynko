<?php

namespace App\Services\Export;

class CsvExporter implements ExporterInterface
{
    public function export(array $data, string $filename): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        if (!empty($data)) {
            fputcsv($out, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($out, $row);
            }
        }

        fclose($out);
        exit;
    }
}
