<?php

namespace App\Support\Jobs;

use App\Models\Job;
use App\Models\JobDescriptionBlock;
use Illuminate\Support\Str;

class JobDescriptionContentRenderer
{
    public function renderHtml(Job $job): string
    {
        $job->loadMissing('descriptionBlocks');

        $descriptionHtml = trim((string) ($job->description_html ?? ''));
        if ($descriptionHtml !== '') {
            return $descriptionHtml;
        }

        $sections = $this->blockSections($job);

        if ($sections->isEmpty()) {
            $escapedTitle = htmlspecialchars((string) $job->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return "<p>{$escapedTitle}</p>";
        }

        return $sections
            ->map(function (array $section): string {
                $escapedLabel = htmlspecialchars((string) $section['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $escapedText = htmlspecialchars((string) $section['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                return "<p><strong>{$escapedLabel}:</strong> {$escapedText}</p>";
            })
            ->implode('');
    }

    public function renderPlainText(Job $job, ?int $limit = null): string
    {
        $job->loadMissing('descriptionBlocks');

        $descriptionHtml = trim((string) ($job->description_html ?? ''));
        if ($descriptionHtml !== '') {
            return $this->applyLimit($this->normalizeWhitespace(strip_tags($descriptionHtml)), $limit);
        }

        $sections = $this->blockSections($job);

        if ($sections->isEmpty()) {
            return $this->applyLimit(trim((string) $job->title), $limit);
        }

        $text = $sections
            ->map(fn (array $section): string => $section['label'].': '.$section['text'])
            ->implode("\n");

        return $this->applyLimit($text, $limit);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{label: string, text: string}>
     */
    private function blockSections(Job $job)
    {
        return $job->descriptionBlocks
            ->map(function (JobDescriptionBlock $block): array {
                $label = Str::headline(str_replace('_', ' ', (string) $block->block_type));
                $text = data_get($block->block_content_json, 'text');

                if (! is_string($text) || trim($text) === '') {
                    $text = json_encode(
                        $block->block_content_json,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                }

                return [
                    'label' => $label,
                    'text' => $this->normalizeWhitespace(strip_tags((string) $text)),
                ];
            })
            ->filter(fn (array $section): bool => $section['text'] !== '')
            ->values();
    }

    private function normalizeWhitespace(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', trim($value)));
    }

    private function applyLimit(string $value, ?int $limit): string
    {
        if (! is_int($limit) || $limit <= 0) {
            return $value;
        }

        return Str::limit($value, $limit, '');
    }
}
