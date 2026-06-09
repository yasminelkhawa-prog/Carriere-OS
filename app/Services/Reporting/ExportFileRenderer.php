<?php

namespace App\Services\Reporting;

use Carbon\CarbonImmutable;

class ExportFileRenderer
{
    /**
     * @param array<int, string> $columns
     * @param array<int, array<int, string>> $rows
     */
    public function renderCsv(array $columns, array $rows): string
    {
        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, $columns);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return is_string($content) ? $content : '';
    }

    /**
     * @param array<int, string> $columns
     * @param array<int, array<int, string>> $rows
     * @param array<string, mixed> $metadata
     */
    public function renderPdf(string $title, array $columns, array $rows, array $metadata = []): string
    {
        $lines = [];
        $lines[] = $title;
        $lines[] = 'Generated at (UTC): '.CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');
        $lines[] = 'Rows: '.count($rows);

        if (isset($metadata['filter_snapshot']) && is_array($metadata['filter_snapshot'])) {
            $filters = array_filter($metadata['filter_snapshot'], static fn (mixed $value): bool => $value !== null && $value !== '');
            if ($filters !== []) {
                $pairs = [];
                foreach ($filters as $key => $value) {
                    if (is_array($value)) {
                        $pairs[] = $key.'='.implode('|', array_map(static fn ($item): string => (string) $item, $value));
                        continue;
                    }

                    $pairs[] = $key.'='.(string) $value;
                }

                $lines[] = 'Filters: '.implode(' | ', $pairs);
            }
        }

        $lines[] = '';

        [$tableLines, $hasTruncatedValues] = $this->buildAlignedTable($columns, $rows, 132);
        foreach ($tableLines as $line) {
            $lines[] = $line;
        }

        if ($hasTruncatedValues) {
            $lines[] = '';
            $lines[] = 'Note: Some values are truncated for readability in PDF. Use CSV export for full raw values.';
        }

        $wrapped = [];
        foreach ($lines as $line) {
            foreach ($this->wrapLine($line, 132) as $segment) {
                $wrapped[] = $segment;
            }
        }

        return $this->buildPdfFromLines($wrapped);
    }

    /**
     * @param array<int, string> $lines
     */
    private function buildPdfFromLines(array $lines): string
    {
        $maxLineLength = 0;
        foreach ($lines as $line) {
            $length = strlen($line);
            if ($length > $maxLineLength) {
                $maxLineLength = $length;
            }
        }

        $isLandscape = $maxLineLength > 95;
        $pageWidth = $isLandscape ? 842 : 612;
        $pageHeight = $isLandscape ? 595 : 792;
        $fontSize = $isLandscape ? 8 : 10;
        $lineHeight = $isLandscape ? 11 : 14;
        $leftMargin = $isLandscape ? 28 : 50;
        $topMargin = $isLandscape ? 30 : 42;
        $startY = $pageHeight - $topMargin;
        $linesPerPage = max(1, (int) floor(($pageHeight - ($topMargin * 2)) / $lineHeight));

        $chunks = array_chunk($lines, $linesPerPage);
        if ($chunks === []) {
            $chunks = [['No data']];
        }

        $objects = [];

        $addObject = static function (string $content) use (&$objects): int {
            $objects[] = $content;

            return count($objects);
        };

        $catalogObj = $addObject('');
        $pagesObj = $addObject('');
        $fontObj = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>');

        $pageObjNumbers = [];

        foreach ($chunks as $chunk) {
            $contentStream = $this->buildPageContent($chunk, $fontSize, $lineHeight, $leftMargin, $startY);
            $contentObj = $addObject(
                "<< /Length ".strlen($contentStream)." >>\nstream\n".$contentStream."\nendstream"
            );

            $pageObj = $addObject(
                "<< /Type /Page /Parent {$pagesObj} 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] ".
                "/Resources << /Font << /F1 {$fontObj} 0 R >> >> /Contents {$contentObj} 0 R >>"
            );

            $pageObjNumbers[] = $pageObj;
        }

        $kids = implode(' ', array_map(static fn (int $objNo): string => "{$objNo} 0 R", $pageObjNumbers));
        $objects[$pagesObj - 1] = "<< /Type /Pages /Kids [ {$kids} ] /Count ".count($pageObjNumbers)." >>";
        $objects[$catalogObj - 1] = "<< /Type /Catalog /Pages {$pagesObj} 0 R >>";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $objectContent) {
            $objectNumber = $index + 1;
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= $objectNumber." 0 obj\n".$objectContent."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i])."\n";
        }

        $pdf .= "trailer\n";
        $pdf .= "<< /Size ".(count($objects) + 1)." /Root {$catalogObj} 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    /**
     * @param array<int, string> $lines
     */
    private function buildPageContent(
        array $lines,
        int $fontSize,
        int $lineHeight,
        int $leftMargin,
        int $startY
    ): string {
        $content = "BT\n/F1 {$fontSize} Tf\n{$lineHeight} TL\n{$leftMargin} {$startY} Td\n";
        $firstLine = true;

        foreach ($lines as $line) {
            if (! $firstLine) {
                $content .= "T*\n";
            }
            $content .= '('.$this->escapePdfText($line).") Tj\n";
            $firstLine = false;
        }

        $content .= "ET";

        return $content;
    }

    /**
     * @return array<int, string>
     */
    private function wrapLine(string $line, int $maxWidth): array
    {
        $clean = trim($line);
        if ($clean === '') {
            return [' '];
        }

        $wrapped = wordwrap($clean, $maxWidth, "\n", true);

        return array_map(
            static fn (string $segment): string => $segment,
            explode("\n", $wrapped)
        );
    }

    private function escapePdfText(string $value): string
    {
        $sanitized = preg_replace('/[^\x20-\x7E]/', '?', $value);
        $sanitized = is_string($sanitized) ? $sanitized : '';

        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $sanitized
        );
    }

    /**
     * @param array<int, string> $columns
     * @param array<int, array<int, string>> $rows
     * @return array{0: array<int, string>, 1: bool}
     */
    private function buildAlignedTable(array $columns, array $rows, int $targetLineWidth): array
    {
        $headers = array_merge(['#'], $columns);
        $indexedRows = [];
        foreach ($rows as $index => $row) {
            $indexedRows[] = array_merge([(string) ($index + 1)], $row);
        }

        $columnCount = count($headers);
        if ($columnCount === 0) {
            return [['No columns available.'], false];
        }

        $maxWidths = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $maxWidths[$i] = strlen($this->normalizeCell((string) $headers[$i]));
        }

        foreach ($indexedRows as $row) {
            for ($i = 0; $i < $columnCount; $i++) {
                $value = $this->normalizeCell((string) ($row[$i] ?? ''));
                $valueLength = strlen($value);
                if ($valueLength > $maxWidths[$i]) {
                    $maxWidths[$i] = $valueLength;
                }
            }
        }

        $widths = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $headerWidth = strlen($this->normalizeCell((string) $headers[$i]));
            $minWidth = $i === 0 ? 3 : max(8, min(12, $headerWidth));
            $maxAllowed = $i === 0 ? 5 : 28;
            $widths[$i] = max($minWidth, min($maxAllowed, $maxWidths[$i]));
        }

        $separatorWidth = ($columnCount * 3) + 1;
        while ((array_sum($widths) + $separatorWidth) > $targetLineWidth) {
            $candidateIndex = null;
            $candidateWidth = 0;
            for ($i = 0; $i < $columnCount; $i++) {
                $minimum = $i === 0 ? 3 : 8;
                if ($widths[$i] > $minimum && $widths[$i] > $candidateWidth) {
                    $candidateWidth = $widths[$i];
                    $candidateIndex = $i;
                }
            }

            if ($candidateIndex === null) {
                break;
            }

            $widths[$candidateIndex]--;
        }

        $truncated = false;
        $lines = [];
        $separator = $this->tableSeparator($widths);
        $lines[] = $separator;
        $lines[] = $this->tableRow($headers, $widths, $truncated);
        $lines[] = $separator;

        if ($indexedRows === []) {
            $emptyRow = array_fill(0, $columnCount, '');
            if ($columnCount > 1) {
                $emptyRow[1] = 'No rows for selected filters.';
            }
            $lines[] = $this->tableRow($emptyRow, $widths, $truncated);
        } else {
            foreach ($indexedRows as $row) {
                $lines[] = $this->tableRow($row, $widths, $truncated);
            }
        }

        $lines[] = $separator;

        return [$lines, $truncated];
    }

    /**
     * @param array<int, int> $widths
     */
    private function tableSeparator(array $widths): string
    {
        $parts = array_map(static fn (int $width): string => str_repeat('-', $width + 2), $widths);

        return '+'.implode('+', $parts).'+';
    }

    /**
     * @param array<int, string> $cells
     * @param array<int, int> $widths
     */
    private function tableRow(array $cells, array $widths, bool &$truncated): string
    {
        $parts = [];
        foreach ($widths as $index => $width) {
            $value = $this->normalizeCell((string) ($cells[$index] ?? ''));
            $value = $this->truncateForWidth($value, $width, $truncated);
            $parts[] = ' '.str_pad($value, $width).' ';
        }

        return '|'.implode('|', $parts).'|';
    }

    private function normalizeCell(string $value): string
    {
        $flat = preg_replace('/\s+/', ' ', trim($value));

        return is_string($flat) ? $flat : '';
    }

    private function truncateForWidth(string $value, int $width, bool &$truncated): string
    {
        if ($width <= 0) {
            return '';
        }

        if (strlen($value) <= $width) {
            return $value;
        }

        $truncated = true;
        if ($width <= 3) {
            return substr($value, 0, $width);
        }

        return substr($value, 0, $width - 3).'...';
    }
}
