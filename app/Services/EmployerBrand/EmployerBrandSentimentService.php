<?php

namespace App\Services\EmployerBrand;

use App\Models\AiRequest;
use App\Models\CandidateSurvey;
use App\Models\InterviewFeedback;
use App\Models\ReverseFeedback;
use App\Models\SentimentResult;
use App\Services\Ai\AiRequestService;
use Illuminate\Support\Str;

class EmployerBrandSentimentService
{
    public const SOURCE_REVERSE_FEEDBACK = 'reverse_feedback';
    public const SOURCE_INTERVIEW_FEEDBACK = 'interview_feedback';
    public const SOURCE_CANDIDATE_SURVEY = 'candidate_survey';

    public function __construct(private readonly AiRequestService $aiRequestService)
    {
    }

    public function queueForReverseFeedback(ReverseFeedback $feedback): ?AiRequest
    {
        return $this->queueForText(
            companyId: (string) $feedback->company_id,
            sourceType: self::SOURCE_REVERSE_FEEDBACK,
            sourceId: (string) $feedback->id,
            text: (string) ($feedback->comment ?? '')
        );
    }

    public function queueForInterviewFeedback(InterviewFeedback $feedback): ?AiRequest
    {
        return $this->queueForText(
            companyId: (string) $feedback->company_id,
            sourceType: self::SOURCE_INTERVIEW_FEEDBACK,
            sourceId: (string) $feedback->id,
            text: (string) ($feedback->notes ?? '')
        );
    }

    public function queueForCandidateSurvey(CandidateSurvey $survey): ?AiRequest
    {
        return $this->queueForText(
            companyId: (string) $survey->company_id,
            sourceType: self::SOURCE_CANDIDATE_SURVEY,
            sourceId: (string) $survey->id,
            text: (string) ($survey->comment ?? '')
        );
    }

    private function queueForText(string $companyId, string $sourceType, string $sourceId, string $text): ?AiRequest
    {
        $normalized = trim($text);
        if ($normalized === '') {
            return null;
        }

        SentimentResult::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $companyId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'sentiment_score' => null,
                'top_themes_json' => [],
                'risk_level' => SentimentResult::RISK_PENDING,
                'created_at' => now(),
            ]
        );

        return $this->aiRequestService->queueRequest(
            companyId: $companyId,
            requestType: 'sentiment_analysis',
            requestPayload: [
                'output_mode' => 'json_schema',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'feedback_text' => $normalized,
                'prompt' => $this->buildPrompt($normalized),
                'json_schema' => [
                    'required' => ['score', 'themes', 'risk_level'],
                    'properties' => [
                        'score' => ['type' => 'number'],
                        'themes' => ['type' => 'array'],
                        'risk_level' => ['type' => 'string'],
                    ],
                ],
            ],
            promptVersion: 'module16-v1'
        );
    }

    private function buildPrompt(string $feedbackText): string
    {
        return implode("\n", [
            'Analyze this candidate feedback text for employer brand monitoring.',
            'Return STRICT JSON with exactly these keys:',
            '- score: number from -1.0 (very negative) to 1.0 (very positive)',
            '- themes: array of short topic strings',
            '- risk_level: one of low, medium, high, critical',
            'No markdown. No extra keys.',
            'Feedback:',
            Str::limit($feedbackText, 4000),
        ]);
    }
}

