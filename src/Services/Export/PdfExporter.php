<?php

namespace App\Services\Export;

/**
 * Minimal PDF table exporter — no external libraries.
 * Uses PDF 1.4 with built-in Helvetica/Helvetica-Bold (Type1) fonts.
 * Polish diacritics are transliterated to ASCII for compatibility.
 */
class PdfExporter implements ExporterInterface
{
    // A4 dimensions in points (72 pt = 1 inch)
    private const PW = 595;
    private const PH = 842;
    private const ML = 30;   // left/right margin
    private const MT = 35;   // top/bottom margin
    private const RH = 12;   // row height
    private const FS = 7;    // data font size
    private const FH = 8;    // header font size
    private const FT = 13;   // title font size

    private const COLS = [
        ['key' => 'created_at', 'label' => 'Data',     'x' =>  30, 'w' => 85],
        ['key' => 'game_name',  'label' => 'Gra',      'x' => 115, 'w' => 60],
        ['key' => 'bet_type',   'label' => 'Typ',      'x' => 175, 'w' => 52],
        ['key' => 'bet_value',  'label' => 'Wartosc',  'x' => 227, 'w' => 52],
        ['key' => 'bet_amount', 'label' => 'Kwota',    'x' => 279, 'w' => 58],
        ['key' => 'outcome',    'label' => 'Wynik',    'x' => 337, 'w' => 44],
        ['key' => 'payout',     'label' => 'Wyplata',  'x' => 381, 'w' => 58],
    ];

    public function export(array $rows, string $filename): void
    {
        $pdf = $this->build($rows);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private function build(array $rows): string
    {
        // Title + header take ~(FT+6 + RH+2) pts; rest is data
        $usableH    = self::PH - 2 * self::MT - (self::FT + 6) - (self::RH + 2);
        $rowsPerPage = max(1, (int)floor($usableH / self::RH));
        $chunks      = array_chunk($rows, $rowsPerPage) ?: [[]];

        $pageStreams = [];
        foreach ($chunks as $chunk) {
            $pageStreams[] = $this->renderPage($chunk);
        }

        return $this->assemblePdf($pageStreams);
    }

    private function renderPage(array $rows): string
    {
        $y   = self::PH - self::MT;
        $out = '';

        // Title
        $out .= sprintf("BT /F2 %d Tf %d %.2f Td (Historia zakladow) Tj ET\n",
            self::FT, self::ML, $y);
        $y -= self::FT + 6;

        // Separator line under title
        $out .= sprintf("0.5 w %.2f %.2f m %.2f %.2f l S\n",
            self::ML, $y, self::PW - self::ML, $y);
        $y -= 3;

        // Header row background
        $headerRowW = self::PW - 2 * self::ML;
        $out .= sprintf("0.82 0.82 0.82 rg %.2f %.2f %.2f %.2f re f 0 0 0 rg\n",
            self::ML, $y - self::RH, $headerRowW, self::RH);

        foreach (self::COLS as $col) {
            $txt = $this->esc($col['label']);
            $out .= sprintf("BT /F2 %d Tf %.2f %.2f Td (%s) Tj ET\n",
                self::FH, $col['x'], $y - self::RH + 3, $txt);
        }
        $y -= self::RH + 2;

        // Data rows
        foreach ($rows as $i => $row) {
            if ($i % 2 === 0) {
                $out .= sprintf("0.96 0.96 0.96 rg %.2f %.2f %.2f %.2f re f 0 0 0 rg\n",
                    self::ML, $y - self::RH, $headerRowW, self::RH);
            }
            foreach (self::COLS as $col) {
                $raw = (string)($row[$col['key']] ?? '');
                if (in_array($col['key'], ['bet_amount', 'payout'], true)) {
                    $raw = number_format((float)$raw, 2);
                } elseif ($col['key'] === 'created_at') {
                    $raw = substr($raw, 0, 16);
                }
                // Truncate to column width (approx 4.2 pts per char at FS=7)
                $maxChars = max(3, (int)($col['w'] / 4.2));
                if (strlen($raw) > $maxChars) {
                    $raw = substr($raw, 0, $maxChars - 1) . '~';
                }
                $out .= sprintf("BT /F1 %d Tf %.2f %.2f Td (%s) Tj ET\n",
                    self::FS, $col['x'], $y - self::RH + 3, $this->esc($raw));
            }
            $y -= self::RH;
        }

        // Bottom border line
        $out .= sprintf("0.5 w %.2f %.2f m %.2f %.2f l S\n",
            self::ML, $y, self::PW - self::ML, $y);

        return $out;
    }

    /** Transliterate Polish chars → ASCII, then escape PDF string special chars. */
    private function esc(string $s): string
    {
        static $map = [
            'ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z',
            'Ą'=>'A','Ć'=>'C','Ę'=>'E','Ł'=>'L','Ń'=>'N','Ó'=>'O','Ś'=>'S','Ź'=>'Z','Ż'=>'Z',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/[^\x20-\x7E]/', '?', $s);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    private function assemblePdf(array $pageStreams): string
    {
        $n        = count($pageStreams);
        // Object layout:
        //   1 = Catalog, 2 = Pages,
        //   3,5,7,… = Page objects  (3 + 2*i for i=0..n-1)
        //   4,6,8,… = Content streams (4 + 2*i for i=0..n-1)
        //   2n+3 = Helvetica font, 2n+4 = Helvetica-Bold font
        // xref has 2n+5 entries (0..2n+4)
        $fontReg  = 2 * $n + 3;
        $fontBold = 2 * $n + 4;
        $xrefSize = 2 * $n + 5;

        $pdf  = "%PDF-1.4\n";
        $offs = [];

        // Object 1 — Catalog
        $offs[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n\n";

        // Object 2 — Pages
        $kids = implode(' ', array_map(fn($i) => (3 + $i * 2) . ' 0 R', range(0, $n - 1)));
        $offs[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [$kids] /Count $n >>\nendobj\n\n";

        // Page + content-stream pairs
        for ($i = 0; $i < $n; $i++) {
            $pageId    = 3 + $i * 2;
            $contentId = 4 + $i * 2;
            $stream    = $pageStreams[$i];
            $len       = strlen($stream);

            $offs[$pageId] = strlen($pdf);
            $pdf .= "$pageId 0 obj\n"
                . "<< /Type /Page /Parent 2 0 R"
                . " /MediaBox [0 0 " . self::PW . " " . self::PH . "]"
                . " /Contents $contentId 0 R"
                . " /Resources << /Font << /F1 $fontReg 0 R /F2 $fontBold 0 R >> >> >>\n"
                . "endobj\n\n";

            $offs[$contentId] = strlen($pdf);
            $pdf .= "$contentId 0 obj\n<< /Length $len >>\nstream\n$stream\nendstream\nendobj\n\n";
        }

        // Font objects
        $offs[$fontReg] = strlen($pdf);
        $pdf .= "$fontReg 0 obj\n"
            . "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\n"
            . "endobj\n\n";

        $offs[$fontBold] = strlen($pdf);
        $pdf .= "$fontBold 0 obj\n"
            . "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\n"
            . "endobj\n\n";

        // Cross-reference table
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 $xrefSize\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $xrefSize; $i++) {
            $pdf .= str_pad((string)($offs[$i] ?? 0), 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size $xrefSize /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF\n";

        return $pdf;
    }
}
