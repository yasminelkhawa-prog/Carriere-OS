<?php

namespace App\Services;

use App\Models\PsyTest;
use Illuminate\Support\Str;

class PsyTestService
{
    public function loadQuestions(string $profile): array
    {
        $path = storage_path('app/data/psy-questions.json');
        if (!file_exists($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path), true);

        return $data['profiles'][$profile] ?? [];
    }

    public function generateToken(): string
    {
        return Str::random(64);
    }

    public function evaluate(PsyTest $psyTest, array $answers): array
    {
        $profileData = $this->loadQuestions($psyTest->profile);

        // Call the Python script for scoring as requested
        $process = new \Symfony\Component\Process\Process(['python', base_path('score_calculator.py')]);
        $process->setInput(json_encode([
            'profile' => $profileData,
            'answers' => $answers,
        ]));
        $process->run();

        if (!$process->isSuccessful()) {
            \Illuminate\Support\Facades\Log::error('Python scoring error: ' . $process->getErrorOutput());
            throw new \Exception("Python scoring failed: " . $process->getErrorOutput());
        }

        $result = json_decode($process->getOutput(), true);
        
        return $result ?: [
            'score' => 0,
            'dimension_scores' => [],
            'desirability_pct' => 0,
            'raw' => [],
            'max' => []
        ];
    }

    private function buildLikertDimMap(array $profileData): array
    {
        // Maps human-readable tag names to dimension keys
        $dimensions = $profileData['dimensions'] ?? [];
        $map = [];
        foreach ($dimensions as $key => $label) {
            $map[$label] = $key;
        }
        return $map;
    }
}
