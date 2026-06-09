<?php

namespace App\Services\SocialHub;

use App\Models\Application;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobDescriptionBlock;
use App\Models\Profile;
use App\Models\SocialPost;
use App\Models\SocialReaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SocialHubService
{
    /**
     * @var array<string, float>
     */
    private const KUDOS_CATEGORY_WEIGHTS = [
        'mvp' => 3.2,
        'innovator' => 2.9,
        'helper' => 2.5,
    ];

    /**
     * @var array<string, array<string, float>>
     */
    private const KUDOS_CATEGORY_SIGNAL_WEIGHTS = [
        'mvp' => [
            'delivery' => 1.2,
            'leadership' => 0.8,
            'technical' => 0.4,
        ],
        'innovator' => [
            'innovation' => 1.4,
            'technical' => 0.8,
            'delivery' => 0.3,
        ],
        'helper' => [
            'collaboration' => 1.4,
            'communication' => 0.7,
            'leadership' => 0.3,
        ],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const SKILL_SIGNAL_KEYWORDS = [
        'leadership' => [
            'lead',
            'ownership',
            'mentor',
            'coaching',
            'initiative',
            'managed',
        ],
        'innovation' => [
            'innov',
            'idea',
            'experiment',
            'prototype',
            'creative',
            'improv',
        ],
        'collaboration' => [
            'team',
            'collab',
            'support',
            'helper',
            'pair',
            'cross-functional',
        ],
        'communication' => [
            'communicat',
            'present',
            'stakeholder',
            'client',
            'clarity',
            'facilitat',
        ],
        'delivery' => [
            'release',
            'ship',
            'deadline',
            'timeline',
            'execution',
            'delivery',
        ],
        'technical' => [
            'api',
            'backend',
            'frontend',
            'platform',
            'architecture',
            'data',
            'php',
            'laravel',
            'react',
            'python',
            'cloud',
        ],
    ];

    /**
     * @return array<int, array{
     *   recipient_user_id: string,
     *   recipient_name: string,
     *   score: float,
     *   kudos_count: int,
     *   dominant_category_key: string,
     *   latest_kudos_at: string
     * }>
     */
    public function buildKudosLeadershipInsights(string $companyId, int $limit = 3): array
    {
        $kudosPosts = SocialPost::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', SocialPost::TYPE_KUDOS)
            ->orderByDesc('created_at')
            ->limit(400)
            ->get(['id', 'metadata_json', 'content_text', 'created_at']);

        if ($kudosPosts->isEmpty()) {
            return [];
        }

        /** @var array<string, array{
         *   score: float,
         *   kudos_count: int,
         *   latest_kudos_at: string,
         *   categories: array<string, int>
         * }> $aggregate
         */
        $aggregate = [];

        foreach ($kudosPosts as $post) {
            $kudosMeta = (array) data_get((array) $post->metadata_json, 'kudos', []);
            $recipientUserId = trim((string) data_get($kudosMeta, 'recipient_user_id', ''));
            if ($recipientUserId === '') {
                continue;
            }

            $categoryKey = trim((string) data_get($kudosMeta, 'category_key', 'helper'));
            if (! array_key_exists($categoryKey, self::KUDOS_CATEGORY_WEIGHTS)) {
                $categoryKey = 'helper';
            }

            $baseWeight = self::KUDOS_CATEGORY_WEIGHTS[$categoryKey];
            $isRecent = $post->created_at !== null && $post->created_at->greaterThan(now()->subDays(30));
            $recencyBoost = $isRecent ? 0.6 : 0.0;

            if (! array_key_exists($recipientUserId, $aggregate)) {
                $aggregate[$recipientUserId] = [
                    'score' => 0.0,
                    'kudos_count' => 0,
                    'latest_kudos_at' => (string) optional($post->created_at)->toIso8601String(),
                    'categories' => [],
                ];
            }

            $aggregate[$recipientUserId]['score'] += $baseWeight + $recencyBoost;
            $aggregate[$recipientUserId]['kudos_count'] += 1;
            $aggregate[$recipientUserId]['categories'][$categoryKey] = (int) ($aggregate[$recipientUserId]['categories'][$categoryKey] ?? 0) + 1;

            $currentLatest = $aggregate[$recipientUserId]['latest_kudos_at'];
            $postTimestamp = (string) optional($post->created_at)->toIso8601String();
            if ($postTimestamp !== '' && ($currentLatest === '' || $postTimestamp > $currentLatest)) {
                $aggregate[$recipientUserId]['latest_kudos_at'] = $postTimestamp;
            }
        }

        if ($aggregate === []) {
            return [];
        }

        $recipientIds = array_keys($aggregate);
        $namesByUserId = Profile::query()
            ->whereIn('user_id', $recipientIds)
            ->pluck('full_name', 'user_id')
            ->map(static fn ($name): string => trim((string) $name))
            ->all();
        $emailsByUserId = User::query()
            ->whereIn('id', $recipientIds)
            ->pluck('email', 'id')
            ->map(static fn ($email): string => trim((string) $email))
            ->all();

        return collect($aggregate)
            ->map(function (array $row, string $recipientUserId) use ($namesByUserId, $emailsByUserId): array {
                $dominantCategoryKey = collect((array) ($row['categories'] ?? []))
                    ->sortDesc()
                    ->keys()
                    ->map(static fn ($key): string => (string) $key)
                    ->first() ?? 'helper';

                return [
                    'recipient_user_id' => $recipientUserId,
                    'recipient_name' => $namesByUserId[$recipientUserId]
                        ?? $emailsByUserId[$recipientUserId]
                        ?? __('social_hub.unknown_author'),
                    'score' => round((float) ($row['score'] ?? 0), 1),
                    'kudos_count' => (int) ($row['kudos_count'] ?? 0),
                    'dominant_category_key' => $dominantCategoryKey,
                    'latest_kudos_at' => (string) ($row['latest_kudos_at'] ?? ''),
                ];
            })
            ->sortByDesc('score')
            ->take(max(1, $limit))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{
     *   job_id: string,
     *   job_title: string,
     *   match_score: int,
     *   matching_signals: array<int, string>
     * }>
     */
    public function suggestInternalRolesForUser(string $companyId, User $user, int $limit = 3): array
    {
        $signalProfile = $this->inferSignalProfileFromKudos(
            companyId: $companyId,
            recipientUserId: (string) $user->id
        );

        if ($signalProfile === []) {
            return [];
        }

        $totalSignalWeight = array_sum($signalProfile);
        if ($totalSignalWeight <= 0) {
            return [];
        }

        $jobs = Job::withoutGlobalScopes()
            ->with(['descriptionBlocks:id,job_id,block_content_json'])
            ->where('company_id', $companyId)
            ->where('status', Job::STATUS_PUBLISHED)
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'location']);

        if ($jobs->isEmpty()) {
            return [];
        }

        $scored = $jobs->map(function (Job $job) use ($signalProfile, $totalSignalWeight): ?array {
            $jobText = $this->jobSearchText($job);
            $jobSignalMap = $this->extractSignalWeights($jobText, 0.55);

            $rawScore = 0.0;
            $matchedSignals = [];
            foreach ($signalProfile as $signalKey => $memberWeight) {
                $jobWeight = (float) ($jobSignalMap[$signalKey] ?? 0.0);
                if ($jobWeight <= 0 || $memberWeight <= 0) {
                    continue;
                }

                $rawScore += min($memberWeight, $jobWeight);
                $matchedSignals[] = $signalKey;
            }

            if ($rawScore <= 0.0) {
                return null;
            }

            $score = (int) max(1, min(100, round(($rawScore / $totalSignalWeight) * 100)));

            return [
                'job_id' => (string) $job->id,
                'job_title' => (string) $job->title,
                'match_score' => $score,
                'matching_signals' => collect($matchedSignals)->unique()->values()->all(),
            ];
        })
            ->filter()
            ->sortByDesc('match_score')
            ->take(max(1, $limit))
            ->values()
            ->all();

        return is_array($scored) ? $scored : [];
    }

    /**
     * @return array<string, int>
     */
    public function syncReactionSummary(SocialPost $post): array
    {
        $counts = SocialReaction::withoutGlobalScopes()
            ->where('company_id', (string) $post->company_id)
            ->where('post_id', (string) $post->id)
            ->selectRaw('reaction_type, COUNT(*) as aggregate_count')
            ->groupBy('reaction_type')
            ->pluck('aggregate_count', 'reaction_type')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $summary = $this->normalizeReactionSummary($counts);

        $post->forceFill([
            'reactions' => $summary,
        ])->save();

        return $summary;
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<string, int>
     */
    public function normalizeReactionSummary(?array $raw): array
    {
        $base = collect(SocialReaction::types())
            ->mapWithKeys(static fn (string $emoji): array => [$emoji => 0]);

        $merged = $base->merge(
            collect($raw ?? [])
                ->mapWithKeys(static fn ($count, $emoji): array => [(string) $emoji => max(0, (int) $count)])
        );

        return $merged
            ->only(SocialReaction::types())
            ->all();
    }

    public function createContractWelcomePost(Application $application, ?User $actor = null): ?SocialPost
    {
        $application->loadMissing(['company', 'job', 'candidate']);

        $companyId = (string) $application->company_id;
        $candidateName = (string) ($application->candidate?->full_name ?? __('social_hub.feed.new_teammate'));
        $roleTitle = (string) ($application->job?->title ?? __('social_hub.feed.team_member_role'));

        $existing = SocialPost::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', SocialPost::TYPE_WELCOME)
            ->where('metadata_json->automation', 'contract_signed')
            ->where('metadata_json->application_id', (string) $application->id)
            ->first();

        if ($existing instanceof SocialPost) {
            return $existing;
        }

        $author = $this->resolveWelcomeAuthor($companyId, $actor);
        if (! $author instanceof User) {
            return null;
        }

        $funFacts = $this->buildFunFacts($candidateName, (string) ($application->candidate?->email ?? ''));

        $content = implode("\n", [
            __('social_hub.feed.automated_welcome_line', ['name' => $candidateName, 'role' => $roleTitle]),
            '',
            __('social_hub.feed.fun_facts_title'),
            __('social_hub.feed.fun_fact_movie', ['value' => $funFacts['favorite_movie']]),
            __('social_hub.feed.fun_fact_drink', ['value' => $funFacts['coffee_or_tea']]),
            __('social_hub.feed.fun_fact_talent', ['value' => $funFacts['hidden_talent']]),
        ]);

        return SocialPost::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'author_user_id' => (string) $author->id,
            'type' => SocialPost::TYPE_WELCOME,
            'visibility' => SocialPost::VISIBILITY_PUBLIC,
            'content_text' => $content,
            'media_url' => null,
            'reactions' => $this->normalizeReactionSummary([]),
            'related_job_id' => (string) ($application->job_id ?: null),
            'metadata_json' => [
                'automation' => 'contract_signed',
                'application_id' => (string) $application->id,
                'candidate_name' => $candidateName,
                'role_title' => $roleTitle,
                'fun_facts' => $funFacts,
                'interaction' => [
                    'label' => __('social_hub.feed.say_hi_button'),
                    'reaction' => SocialReaction::TYPE_WAVE,
                ],
            ],
            'poll_question_text' => null,
            'poll_options_json' => null,
        ]);
    }

    private function resolveWelcomeAuthor(string $companyId, ?User $actor): ?User
    {
        $recruiterOrAdminId = CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->whereIn('company_role', [
                CompanyMembership::ROLE_RECRUITER,
                CompanyMembership::ROLE_COMPANY_ADMIN,
            ])
            ->orderByRaw("CASE WHEN company_role = 'recruiter' THEN 0 ELSE 1 END")
            ->value('user_id');

        if (is_string($recruiterOrAdminId) && $recruiterOrAdminId !== '') {
            $resolved = User::query()->find($recruiterOrAdminId);
            if ($resolved instanceof User) {
                return $resolved;
            }
        }

        return $actor;
    }

    /**
     * @return array<string, float>
     */
    private function inferSignalProfileFromKudos(string $companyId, string $recipientUserId): array
    {
        if ($recipientUserId === '') {
            return [];
        }

        $kudosPosts = SocialPost::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', SocialPost::TYPE_KUDOS)
            ->where('metadata_json->kudos->recipient_user_id', $recipientUserId)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['content_text', 'metadata_json', 'created_at']);

        if ($kudosPosts->isEmpty()) {
            return [];
        }

        /** @var array<string, float> $signals */
        $signals = [];

        foreach ($kudosPosts as $post) {
            $kudosMeta = (array) data_get((array) $post->metadata_json, 'kudos', []);
            $categoryKey = trim((string) data_get($kudosMeta, 'category_key', 'helper'));

            foreach ((array) (self::KUDOS_CATEGORY_SIGNAL_WEIGHTS[$categoryKey] ?? []) as $signalKey => $weight) {
                $signals[$signalKey] = (float) ($signals[$signalKey] ?? 0.0) + (float) $weight;
            }

            $textSignals = $this->extractSignalWeights((string) $post->content_text, 1.0);
            foreach ($textSignals as $signalKey => $weight) {
                $signals[$signalKey] = (float) ($signals[$signalKey] ?? 0.0) + (float) $weight;
            }

            if ($post->created_at !== null && $post->created_at->greaterThan(now()->subDays(45))) {
                foreach (array_keys($textSignals) as $signalKey) {
                    $signals[$signalKey] = (float) ($signals[$signalKey] ?? 0.0) + 0.2;
                }
            }
        }

        return collect($signals)
            ->map(static fn ($weight): float => round((float) $weight, 2))
            ->sortDesc()
            ->take(6)
            ->all();
    }

    private function jobSearchText(Job $job): string
    {
        $parts = collect([(string) $job->title, (string) $job->location]);

        $descriptionBlocks = $job->descriptionBlocks instanceof Collection
            ? $job->descriptionBlocks
            : collect();

        $blockText = $descriptionBlocks
            ->map(function (JobDescriptionBlock $block): string {
                $payload = (array) ($block->block_content_json ?? []);
                return trim((string) implode(' ', array_filter([
                    (string) ($payload['text'] ?? ''),
                    (string) ($payload['title'] ?? ''),
                    (string) ($payload['summary'] ?? ''),
                    (string) ($payload['content'] ?? ''),
                ])));
            })
            ->filter(static fn (string $value): bool => $value !== '')
            ->implode(' ');

        if ($blockText !== '') {
            $parts->push($blockText);
        }

        return Str::lower(trim($parts->implode(' ')));
    }

    /**
     * @return array<string, float>
     */
    private function extractSignalWeights(string $rawText, float $baseWeight): array
    {
        $text = Str::lower(trim($rawText));
        if ($text === '') {
            return [];
        }

        /** @var array<string, float> $weights */
        $weights = [];

        foreach (self::SKILL_SIGNAL_KEYWORDS as $signalKey => $keywords) {
            foreach ($keywords as $keyword) {
                if (! str_contains($text, $keyword)) {
                    continue;
                }

                $weights[$signalKey] = (float) ($weights[$signalKey] ?? 0.0) + $baseWeight;
            }
        }

        return $weights;
    }

    /**
     * @return array{favorite_movie: string, coffee_or_tea: string, hidden_talent: string}
     */
    private function buildFunFacts(string $candidateName, string $email): array
    {
        $seedSource = $email !== '' ? $email : $candidateName;
        $seed = abs((int) crc32($seedSource));

        $movies = [
            'Interstellar',
            'The Social Network',
            'Hidden Figures',
            'Inception',
            'The Martian',
        ];
        $drinks = [
            'Coffee',
            'Tea',
            'Both, depending on deadlines',
        ];
        $talents = [
            'Turns whiteboard chaos into clear plans',
            'Can explain complex ideas in one sentence',
            'Builds playlists that keep teams focused',
            'Finds elegant shortcuts in messy workflows',
            'Can host a standup in three languages',
        ];

        return [
            'favorite_movie' => $movies[$seed % count($movies)],
            'coffee_or_tea' => $drinks[$seed % count($drinks)],
            'hidden_talent' => $talents[$seed % count($talents)],
        ];
    }
}
