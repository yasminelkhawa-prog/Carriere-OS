<?php

namespace Database\Seeders;

use App\Models\AiRequest;
use App\Models\Company;
use App\Services\Ai\AiRequestService;
use Illuminate\Database\Seeder;

class DemoAiRequestSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()
            ->whereIn('name', ['Malik and Co', 'Malik Company'])
            ->first();

        if (! $company instanceof Company) {
            $this->command?->warn('No Malik company found. Skipping DemoAiRequestSeeder.');
            return;
        }

        /** @var AiRequestService $aiRequestService */
        $aiRequestService = app(AiRequestService::class);

        $this->command?->info('Queuing real AI requests under: '.$company->name);

        $definitions = [
            [
                'request_type' => 'candidate_analysis',
                'request_payload' => [
                    'prompt' => 'Analyze this safe dummy candidate profile for a generic recruiter review. Return strict JSON with score and summary.',
                    'output_mode' => 'json_schema',
                    'json_schema' => [
                        'required' => ['score', 'summary'],
                        'properties' => [
                            'score' => ['type' => 'number'],
                            'summary' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            [
                'request_type' => 'email_draft',
                'request_payload' => [
                    'prompt' => 'Write a concise interview logistics email for a candidate. Use plain text only.',
                    'output_mode' => 'text',
                ],
            ],
            [
                'request_type' => 'executive_summary',
                'request_payload' => [
                    'prompt' => 'Create an executive summary from safe dummy hiring funnel metrics. Keep it concise and action-oriented.',
                    'output_mode' => 'text',
                ],
            ],
            [
                'request_type' => 'sentiment_analysis',
                'request_payload' => [
                    'prompt' => 'Analyze sentiment for this safe dummy text: "The interview process was smooth and professional." Return strict JSON.',
                    'output_mode' => 'json_schema',
                ],
            ],
        ];

        $createdRequestIds = [];

        foreach ($definitions as $definition) {
            $request = $aiRequestService->queueRequest(
                companyId: (string) $company->id,
                requestType: (string) $definition['request_type'],
                requestPayload: (array) $definition['request_payload'],
                promptVersion: 'demo_seed_v2'
            );

            $createdRequestIds[] = $request->id;

            // Process now so seeded rows represent provider-generated AI behavior.
            $aiRequestService->process($request->fresh());
        }

        $succeeded = AiRequest::withoutGlobalScopes()
            ->whereIn('id', $createdRequestIds)
            ->where('status', AiRequest::STATUS_SUCCEEDED)
            ->count();

        $failed = AiRequest::withoutGlobalScopes()
            ->whereIn('id', $createdRequestIds)
            ->where('status', AiRequest::STATUS_FAILED)
            ->count();

        $this->command?->info("Seeded {$succeeded} succeeded and {$failed} failed real AI requests for {$company->name}.");
    }
}
