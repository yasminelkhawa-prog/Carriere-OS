<?php

namespace App\Services\Cv;

use App\Models\CandidateDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class CandidateCvTextExtractor
{
    private const MAX_TEXT_CHARS = 26000;

    /**
     * @return array{
     *   text: string,
     *   sha256: ?string,
     *   warnings: array<int, string>,
     *   meta: array<string, mixed>
     * }
     */
    public function extract(CandidateDocument $document): array
    {
        $path = trim((string) $document->file_url);
        $extension = Str::lower((string) pathinfo((string) $document->original_filename, PATHINFO_EXTENSION));
        $mime = Str::lower(trim((string) $document->mime_type));
        $warnings = [];

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return [
                'text' => '',
                'sha256' => null,
                'warnings' => ['cv_file_missing'],
                'meta' => [
                    'extension' => $extension,
                    'mime' => $mime,
                    'size_bytes' => (int) ($document->file_size_bytes ?? 0),
                ],
            ];
        }

        try {
            $raw = (string) Storage::disk('local')->get($path);
        } catch (Throwable $exception) {
            Log::warning('Unable to read CV document for parsing.', [
                'candidate_document_id' => (string) $document->id,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            return [
                'text' => '',
                'sha256' => null,
                'warnings' => ['cv_file_read_failed'],
                'meta' => [
                    'extension' => $extension,
                    'mime' => $mime,
                    'size_bytes' => (int) ($document->file_size_bytes ?? 0),
                ],
            ];
        }

        $text = match (true) {
            $this->isPdf($extension, $mime) => $this->extractPdfText($raw),
            $this->isDocx($extension, $mime) => $this->extractDocxText(Storage::disk('local')->path($path)),
            $extension === 'rtf' || str_contains($mime, 'rtf') => $this->extractRtfText($raw),
            $extension === 'txt' || str_contains($mime, 'text/plain') => $raw,
            $extension === 'doc' || str_contains($mime, 'msword') => $this->extractLegacyDocText($raw),
            default => $this->extractPrintableText($raw),
        };

        if (trim($text) === '') {
            $warnings[] = 'cv_text_extraction_low_confidence';
            $text = $this->extractPrintableText($raw);
        }

        $normalized = $this->normalizeWhitespace($text);
        if (mb_strlen($normalized) > self::MAX_TEXT_CHARS) {
            $normalized = mb_substr($normalized, 0, self::MAX_TEXT_CHARS);
            $warnings[] = 'cv_text_truncated';
        }

        return [
            'text' => $normalized,
            'sha256' => hash('sha256', $raw),
            'warnings' => array_values(array_unique($warnings)),
            'meta' => [
                'extension' => $extension,
                'mime' => $mime,
                'size_bytes' => (int) ($document->file_size_bytes ?? strlen($raw)),
            ],
        ];
    }

    private function isPdf(string $extension, string $mime): bool
    {
        return $extension === 'pdf' || str_contains($mime, 'application/pdf');
    }

    private function isDocx(string $extension, string $mime): bool
    {
        return $extension === 'docx'
            || str_contains($mime, 'officedocument.wordprocessingml.document')
            || str_contains($mime, 'application/zip');
    }

    private function extractPdfText(string $raw): string
    {
        $chunks = [];

        if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $raw, $matches) === 1) {
            foreach ((array) ($matches[1] ?? []) as $stream) {
                $decoded = $this->decodePdfStream((string) $stream);
                if ($decoded === '') {
                    continue;
                }

                if (preg_match_all('/BT(.*?)ET/s', $decoded, $textBlocks) === 1) {
                    foreach ((array) ($textBlocks[1] ?? []) as $block) {
                        $chunks[] = $this->extractParenthesizedPdfText((string) $block);
                    }
                }
            }
        }

        $joined = trim(implode("\n", array_filter($chunks)));
        if ($joined !== '') {
            return $joined;
        }

        return $this->extractPrintableText($raw);
    }

    private function decodePdfStream(string $stream): string
    {
        $trimmed = ltrim($stream, "\r\n");
        $attempts = [
            static fn (string $input): string => @gzuncompress($input) ?: '',
            static fn (string $input): string => @gzdecode($input) ?: '',
            static fn (string $input): string => @gzinflate($input) ?: '',
            static fn (string $input): string => @gzinflate(substr($input, 2)) ?: '',
        ];

        foreach ($attempts as $decoder) {
            $decoded = $decoder($trimmed);
            if ($decoded !== '') {
                return $decoded;
            }
        }

        return $trimmed;
    }

    private function extractParenthesizedPdfText(string $block): string
    {
        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/', $block, $matches) !== 1) {
            return '';
        }

        $parts = [];
        foreach ((array) ($matches[0] ?? []) as $match) {
            $chunk = substr((string) $match, 1, -1);
            $chunk = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $chunk);
            $chunk = preg_replace('/\\\([0-7]{1,3})/', ' ', (string) $chunk) ?? (string) $chunk;
            $parts[] = $chunk;
        }

        return implode(' ', $parts);
    }

    private function extractDocxText(string $absolutePath): string
    {
        $zip = new ZipArchive();
        $opened = $zip->open($absolutePath);
        if ($opened !== true) {
            return '';
        }

        $buffer = '';
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if ($name === '' || preg_match('/^word\/(document|header\d+|footer\d+)\.xml$/', $name) !== 1) {
                continue;
            }

            $xml = (string) $zip->getFromIndex($index);
            if ($xml === '') {
                continue;
            }

            $xml = str_replace(['</w:p>', '</w:tr>', '</w:tc>', '<w:br/>', '<w:tab/>'], "\n", $xml);
            $text = strip_tags($xml);
            if ($text !== '') {
                $buffer .= "\n".$text;
            }
        }

        $zip->close();

        return $buffer;
    }

    private function extractRtfText(string $raw): string
    {
        $text = preg_replace_callback(
            "/\\\\'[0-9a-fA-F]{2}/",
            static fn (array $matches): string => chr(hexdec(substr((string) ($matches[0] ?? ''), 2))),
            $raw
        );
        $text = preg_replace('/\\\\[a-zA-Z]+\d*\s?/', ' ', (string) $text) ?? (string) $text;
        $text = str_replace(['{', '}'], ' ', (string) $text);

        return (string) $text;
    }

    private function extractLegacyDocText(string $raw): string
    {
        $text = str_replace("\x00", ' ', $raw);

        return $this->extractPrintableText($text);
    }

    private function extractPrintableText(string $raw): string
    {
        $text = preg_replace('/[^\PC\s]/u', ' ', $raw);
        if (! is_string($text)) {
            $text = $raw;
        }

        return preg_replace('/[^[:print:]\r\n\t]/', ' ', $text) ?? $text;
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
