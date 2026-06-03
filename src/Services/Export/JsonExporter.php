<?php

namespace App\Services\Export;

class JsonExporter implements ExporterInterface
{
    public function export(array $data, string $filename): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        header('Cache-Control: no-store');

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
