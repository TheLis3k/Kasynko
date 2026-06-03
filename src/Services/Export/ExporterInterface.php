<?php

namespace App\Services\Export;

interface ExporterInterface
{
    public function export(array $data, string $filename): void;
}
