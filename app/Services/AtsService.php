<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Cv;
use App\Models\Job;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AtsService
{
    private string $pythonApiUrl = 'http://localhost:8000';

    public function processCvUpload(UploadedFile $file, Company $company, Job $job): Application
    {
        // 1. Store the file
        $path = $file->store('cvs', 'local');
        $absolutePath = storage_path('app/' . $path);

        // 2. Call Python API to parse CV
        $parseResponse = Http::post("{$this->pythonApiUrl}/parse-cv", [
            'file_path' => $absolutePath,
        ]);

        if (! $parseResponse->successful()) {
            Log::error('ATS CV Parsing failed', ['response' => $parseResponse->body()]);
            throw new \Exception('Failed to parse CV');
        }

        $parsedData = $parseResponse->json();
        $extractedText = $parsedData['text'] ?? '';

        // 3. Save CV record
        $cv = Cv::create([
            'file_path' => $path,
            'extracted_text' => $extractedText,
        ]);

        // 4. Call Python API to analyze CV against Job Description
        $jobDescription = $job->descriptionBlocks->pluck('block_content_json.text')->implode("\n");

        $analyzeResponse = Http::post("{$this->pythonApiUrl}/analyze-cv", [
            'cv_text' => $extractedText,
            'job_description' => $jobDescription,
        ]);

        if (! $analyzeResponse->successful()) {
            Log::error('ATS CV Analysis failed', ['response' => $analyzeResponse->body()]);
            throw new \Exception('Failed to analyze CV');
        }

        $aiResult = $analyzeResponse->json();
        $score = $aiResult['score'] ?? 0;

        // 5. Create Candidate (Dummy details for now as CV parsing might not extract name/email perfectly yet in this basic setup)
        // Ideally, we'd extract these from the CV. We'll use placeholders.
        $candidate = Candidate::create([
            'company_id' => $company->id,
            'full_name' => 'Candidate from ATS ' . uniqid(),
            'email' => 'ats_' . uniqid() . '@example.com',
        ]);

        // 6. Create Application
        return Application::create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $job->pipelineStages->first()->id ?? null,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'ats_upload',
            'cv_id' => $cv->id,
            'score' => $score,
            'ai_result_json' => $aiResult,
        ]);
    }
}
