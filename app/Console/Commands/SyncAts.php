<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Application;
use App\Models\CandidateDocument;
use App\Models\CvParsingResult;
use App\Models\ApplicationScoring;
use Illuminate\Support\Facades\Http;

class SyncAts extends Command
{
    protected $signature = 'ats:sync';
    protected $description = 'Analyze pending CVs using Python ATS';

    public function handle()
    {
        $this->info('Finding pending applications...');
        
        $applications = Application::where('id', 'a1fd31a5-8223-435e-9676-ce161a40500f')->get();

        foreach ($applications as $app) {
            $this->info("Processing Application {$app->id}");
            $doc = CandidateDocument::where('candidate_id', $app->candidate_id)->where('document_type', 'resume')->first();
            
            if (!$doc) {
                $this->warn("No resume found for Candidate {$app->candidate_id}");
                continue;
            }

            $absolutePath = storage_path('app/' . $doc->file_url);

            if (!file_exists($absolutePath)) {
                $this->warn("File not found: {$absolutePath}");
                continue;
            }

            // Call Python Parse
            $parseResponse = Http::post("http://localhost:8001/parse-cv", [
                'file_path' => $absolutePath,
            ]);

            if (!$parseResponse->successful()) {
                $this->error("Parse failed for app {$app->id}: " . $parseResponse->body());
                continue;
            }

            $extractedText = $parseResponse->json('text') ?? '';

            // Call Python Analyze
            $jobDesc = $app->job->descriptionBlocks->pluck('block_content_json.text')->implode("\n");
            $analyzeResponse = Http::post("http://localhost:8001/analyze-cv", [
                'cv_text' => $extractedText,
                'job_description' => $jobDesc,
            ]);

            if (!$analyzeResponse->successful()) {
                $this->error("Analyze failed for app {$app->id}: " . $analyzeResponse->body());
                continue;
            }

            $aiResult = $analyzeResponse->json();
            $score = $aiResult['score'] ?? 0;
            $education = $aiResult['education'] ?? '';
            $expYears = $aiResult['experience_years'] ?? 0;

            // Save to CvParsingResult
            CvParsingResult::withoutGlobalScopes()->updateOrCreate(
                ['application_id' => $app->id],
                [
                    'company_id' => $app->company_id,
                    'candidate_id' => $app->candidate_id,
                    'education_entries_json' => [['institution_name' => $education]],
                    'experience_entries_json' => [],
                    'total_years_experience' => $expYears,
                    'parse_status' => 'completed'
                ]
            );

            // Save to CvParsingResult
            CvParsingResult::withoutGlobalScopes()->updateOrCreate(
                ['application_id' => $app->id],
                [
                    'company_id' => $app->company_id,
                    'global_match_score' => $score,
                    'analysis_status' => 'completed',
                    'updated_at' => now(),
                ]
            );

            // Also update ApplicationScoring so it shows up in UI
            ApplicationScoring::where('application_id', $app->id)->update([
                'global_match_score' => $score,
                'analysis_status' => 'completed'
            ]);

            // Also update the app fields from our custom ATS
            $app->update([
                'score' => $score,
                'ai_result_json' => $aiResult
            ]);

            $this->info("Completed app {$app->id} with score {$score}");
        }

        $this->info('Done.');
    }
}
