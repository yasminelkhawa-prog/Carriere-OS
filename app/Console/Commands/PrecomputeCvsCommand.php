<?php

namespace App\Console\Commands;

use App\Services\Ai\GeminiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PrecomputeCvsCommand extends Command
{
    protected $signature = 'app:precompute-cvs {path}';
    protected $description = 'Precompute AI parsing for a directory of CVs and cache them.';

    public function handle(GeminiClient $geminiClient): int
    {
        $path = $this->argument('path');
        if (!File::isDirectory($path)) {
            $this->error("Directory not found: {$path}");
            return self::FAILURE;
        }

        $files = File::allFiles($path);
        $total = count($files);
        $this->info("Found {$total} files in {$path}.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($files as $file) {
            if (strtolower($file->getExtension()) !== 'pdf') {
                $bar->advance();
                continue;
            }

            $raw = file_get_contents($file->getPathname());
            $sha256 = hash('sha256', $raw);
            $cachePath = "cv_cache/{$sha256}.json";

            if (Storage::disk('local')->exists($cachePath)) {
                $bar->advance();
                continue;
            }

            $prompt = $this->buildPrompt();
            $base64Data = base64_encode($raw);

            $parts = [
                ['text' => $prompt],
                [
                    'inlineData' => [
                        'mimeType' => 'application/pdf',
                        'data' => $base64Data,
                    ]
                ]
            ];

            try {
                $result = $geminiClient->generateParts($parts, config('services.gemini.model', 'gemini-1.5-flash'));
                
                // Clean the markdown json block if present
                $json = trim($result);
                if (str_starts_with($json, '```json')) {
                    $json = substr($json, 7);
                }
                if (str_starts_with($json, '```')) {
                    $json = substr($json, 3);
                }
                if (str_ends_with($json, '```')) {
                    $json = substr($json, 0, -3);
                }
                $json = trim($json);

                Storage::disk('local')->put($cachePath, $json);
            } catch (\Throwable $e) {
                $this->error("\nFailed to process {$file->getFilename()}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Precomputation complete.');

        return self::SUCCESS;
    }

    private function buildPrompt(): string
    {
        $schema = json_encode([
            'required' => [
                'summary', 'profile', 'languages', 'hard_skills', 'soft_skills', 
                'tools_frameworks', 'total_years_experience', 'experience', 
                'employment_chronology', 'certifications', 'projects', 'education', 
                'honors', 'role_keywords', 'parsed_metadata', 'flags',
            ],
            'properties' => [
                'summary' => ['type' => 'string'],
                'profile' => ['type' => 'object'],
                'languages' => ['type' => 'array'],
                'hard_skills' => ['type' => 'array'],
                'soft_skills' => ['type' => 'array'],
                'tools_frameworks' => ['type' => 'array'],
                'total_years_experience' => ['type' => 'number'],
                'experience' => ['type' => 'array'],
                'employment_chronology' => ['type' => 'array'],
                'certifications' => ['type' => 'array'],
                'projects' => ['type' => 'array'],
                'education' => ['type' => 'array'],
                'honors' => ['type' => 'array'],
                'role_keywords' => ['type' => 'array'],
                'parsed_metadata' => ['type' => 'object'],
                'flags' => ['type' => 'object'],
            ],
        ], JSON_PRETTY_PRINT);

        return "You are an expert technical recruiter and resume parser.
Extract all relevant information from the attached candidate resume PDF.

Respond ONLY with valid JSON conforming exactly to the schema below.
Ensure total accuracy and do not omit any details found in the PDF.

<json_schema>
{$schema}
</json_schema>";
    }
}
