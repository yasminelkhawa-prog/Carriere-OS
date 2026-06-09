<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\SocialPost;
use App\Models\SocialPulsePollVote;
use App\Models\SocialReaction;
use App\Models\User;
use App\Services\SocialHub\SocialHubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SocialHubController extends Controller
{
    use ResolvesManagedCompany;

    public function __construct(
        private readonly SocialHubService $socialHubService
    ) {
    }

    public function index(Request $request): View
    {
        [$companyId, $companies] = $this->resolveCompanyContext($request);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        if ($companyId === null) {
            return view('social-hub.index', [
                'requiresCompanySelection' => true,
                'isCandidatePortal' => false,
                'company' => null,
                'companies' => $companies,
                'posts' => collect(),
                'authors' => collect(),
                'jobsForLinking' => collect(),
                'filters' => [
                    'post_types' => [],
                    'author_user_id' => null,
                ],
                'postTypes' => SocialPost::types(),
                'reactionTypes' => SocialReaction::types(),
                'allowedPostTypes' => [],
                'kudosCategories' => $this->kudosCategories(),
                'canCompose' => false,
                'canSendKudos' => false,
                'canVotePolls' => false,
                'pulseSummary' => [],
            ]);
        }

        $role = $this->activeMembershipRole($actor, $companyId);
        abort_unless($this->canAccessRecruitmentHub($actor, $role), 403);

        return $this->renderHub(
            request: $request,
            actor: $actor,
            company: Company::query()->findOrFail($companyId),
            role: $role,
            isCandidatePortal: false,
            companies: $companies,
            redirectRoute: 'social-hub.index'
        );
    }

    public function candidateIndex(Request $request, Company $company): View|RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->resolveCandidatePortalContext($request, $company, $actor);

        return $this->renderHub(
            request: $request,
            actor: $actor,
            company: $company,
            role: CompanyMembership::ROLE_CANDIDATE,
            isCandidatePortal: true,
            companies: collect(),
            redirectRoute: 'candidate.social-hub.index'
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless(is_string($companyId) && $companyId !== '', 403);

        $role = $this->activeMembershipRole($actor, $companyId);
        abort_unless($this->canAccessRecruitmentHub($actor, $role), 403);

        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['standard', 'kudos'])],
            'type' => ['nullable', Rule::in(SocialPost::types())],
            'visibility' => ['required', Rule::in(SocialPost::visibilities())],
            'content_text' => ['nullable', 'string', 'max:'.SocialPost::CONTENT_MAX_LENGTH],
            'media_url' => ['nullable', 'url', 'max:2048'],
            'related_job_id' => [
                'nullable',
                'uuid',
                Rule::exists('jobs', 'id')->where(
                    static function ($query) use ($companyId): void {
                        $query->where('company_id', $companyId)
                            ->where('status', Job::STATUS_PUBLISHED);
                    }
                ),
            ],
            'poll_question_text' => ['nullable', 'string', 'max:255'],
            'poll_enabled' => ['nullable', 'boolean'],
            'kudos_recipient_user_id' => [
                'nullable',
                'uuid',
                Rule::exists('company_memberships', 'user_id')->where(
                    static function ($query) use ($companyId): void {
                        $query->where('company_id', $companyId)
                            ->where('membership_status', CompanyMembership::STATUS_ACTIVE);
                    }
                ),
            ],
            'kudos_category' => ['nullable', Rule::in(array_keys($this->kudosCategories()))],
            'kudos_message' => ['nullable', 'string', 'max:'.SocialPost::CONTENT_MAX_LENGTH],
        ]);

        $mode = (string) ($validated['mode'] ?? 'standard');
        $relatedJobId = isset($validated['related_job_id']) ? (string) $validated['related_job_id'] : null;
        $visibility = (string) $validated['visibility'];

        if ($mode === 'kudos') {
            $this->storeKudosPost($actor, $companyId, $role, $validated, $visibility);
        } else {
            $this->storeStandardPost($actor, $companyId, $role, $validated, $visibility, $relatedJobId);
        }

        return redirect()
            ->route('social-hub.index', $this->companyQuery($request))
            ->with('status', __('social_hub.flash.post_created'));
    }

    public function storeReaction(Request $request, SocialPost $post): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless(is_string($companyId) && $companyId !== '', 403);

        $role = $this->activeMembershipRole($actor, $companyId);
        abort_unless($this->canAccessRecruitmentHub($actor, $role), 403);

        return $this->storeReactionForContext(
            request: $request,
            actor: $actor,
            companyId: $companyId,
            role: $role,
            post: $post,
            isCandidatePortal: false,
            candidateCompanySlug: null
        );
    }

    public function candidateStoreReaction(Request $request, Company $company, SocialPost $post): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->resolveCandidatePortalContext($request, $company, $actor);

        return $this->storeReactionForContext(
            request: $request,
            actor: $actor,
            companyId: (string) $company->id,
            role: CompanyMembership::ROLE_CANDIDATE,
            post: $post,
            isCandidatePortal: true,
            candidateCompanySlug: (string) $company->slug
        );
    }

    public function storePollVote(Request $request, SocialPost $post): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless(is_string($companyId) && $companyId !== '', 403);

        $role = $this->activeMembershipRole($actor, $companyId);
        abort_unless($this->canAccessRecruitmentHub($actor, $role), 403);

        return $this->storePollVoteForContext(
            request: $request,
            actor: $actor,
            companyId: $companyId,
            post: $post,
            isCandidatePortal: false,
            candidateCompanySlug: null
        );
    }

    public function candidateStorePollVote(Request $request, Company $company, SocialPost $post): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->resolveCandidatePortalContext($request, $company, $actor);

        return $this->storePollVoteForContext(
            request: $request,
            actor: $actor,
            companyId: (string) $company->id,
            post: $post,
            isCandidatePortal: true,
            candidateCompanySlug: (string) $company->slug
        );
    }

    private function storeStandardPost(
        User $actor,
        string $companyId,
        ?string $role,
        array $validated,
        string $visibility,
        ?string $relatedJobId
    ): void {
        $type = (string) ($validated['type'] ?? '');
        if (! $this->canCreatePostType($actor, $role, $type, $relatedJobId)) {
            $this->logPermissionWarning(
                actor: $actor,
                companyId: $companyId,
                role: $role,
                action: 'create_post_forbidden',
                extra: ['type' => $type, 'related_job_id' => $relatedJobId]
            );

            throw ValidationException::withMessages([
                'type' => __('social_hub.flash.permission_denied'),
            ]);
        }

        $content = trim((string) ($validated['content_text'] ?? ''));
        if ($content === '') {
            throw ValidationException::withMessages([
                'content_text' => __('social_hub.validation.content_required'),
            ]);
        }

        $mediaUrl = trim((string) ($validated['media_url'] ?? ''));
        if ($mediaUrl !== '' && ! in_array($type, SocialPost::mediaAllowedTypes(), true)) {
            throw ValidationException::withMessages([
                'media_url' => __('social_hub.validation.media_not_allowed_for_type'),
            ]);
        }

        $pollEnabled = (bool) ($validated['poll_enabled'] ?? false);
        $pollQuestion = null;
        $pollOptions = null;
        if ($type === SocialPost::TYPE_IDEA && $pollEnabled) {
            $pollQuestion = trim((string) ($validated['poll_question_text'] ?? ''));
            if ($pollQuestion === '') {
                throw ValidationException::withMessages([
                    'poll_question_text' => __('social_hub.validation.poll_question_required'),
                ]);
            }
            $pollOptions = $this->defaultPollOptions();
        }

        SocialPost::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'author_user_id' => (string) $actor->id,
            'type' => $type,
            'visibility' => $visibility,
            'content_text' => $content,
            'media_url' => $mediaUrl !== '' ? $mediaUrl : null,
            'reactions' => $this->socialHubService->normalizeReactionSummary([]),
            'related_job_id' => $relatedJobId,
            'metadata_json' => $relatedJobId !== null ? [
                'cta' => [
                    'label' => __('social_hub.feed.view_roles'),
                ],
            ] : null,
            'poll_question_text' => $pollQuestion,
            'poll_options_json' => $pollOptions,
        ]);
    }

    private function storeKudosPost(
        User $actor,
        string $companyId,
        ?string $role,
        array $validated,
        string $visibility
    ): void {
        if (! $this->canCreatePostType($actor, $role, SocialPost::TYPE_KUDOS, null)) {
            $this->logPermissionWarning(
                actor: $actor,
                companyId: $companyId,
                role: $role,
                action: 'create_kudos_forbidden'
            );

            throw ValidationException::withMessages([
                'kudos_message' => __('social_hub.flash.permission_denied'),
            ]);
        }

        $recipientId = (string) ($validated['kudos_recipient_user_id'] ?? '');
        $message = trim((string) ($validated['kudos_message'] ?? ''));
        $categoryKey = (string) ($validated['kudos_category'] ?? '');
        $categories = $this->kudosCategories();

        if ($recipientId === '' || $message === '' || ! isset($categories[$categoryKey])) {
            throw ValidationException::withMessages([
                'kudos_message' => __('social_hub.validation.kudos_fields_required'),
            ]);
        }

        if ($recipientId === (string) $actor->id) {
            throw ValidationException::withMessages([
                'kudos_recipient_user_id' => __('social_hub.validation.kudos_recipient_not_self'),
            ]);
        }

        $recipient = User::query()->with('profile')->find($recipientId);
        if (! $recipient instanceof User) {
            throw ValidationException::withMessages([
                'kudos_recipient_user_id' => __('social_hub.validation.kudos_fields_required'),
            ]);
        }

        $category = $categories[$categoryKey];

        SocialPost::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'author_user_id' => (string) $actor->id,
            'type' => SocialPost::TYPE_KUDOS,
            'visibility' => $visibility,
            'content_text' => $message,
            'media_url' => null,
            'reactions' => $this->socialHubService->normalizeReactionSummary([]),
            'related_job_id' => null,
            'metadata_json' => [
                'kudos' => [
                    'sender_user_id' => (string) $actor->id,
                    'recipient_user_id' => (string) $recipient->id,
                    'recipient_name' => (string) ($recipient->profile?->full_name ?? $recipient->email),
                    'category_key' => $categoryKey,
                    'category_label' => (string) $category['label'],
                    'icon' => (string) $category['icon'],
                ],
            ],
            'poll_question_text' => null,
            'poll_options_json' => null,
        ]);
    }

    private function storeReactionForContext(
        Request $request,
        User $actor,
        string $companyId,
        ?string $role,
        SocialPost $post,
        bool $isCandidatePortal,
        ?string $candidateCompanySlug
    ): RedirectResponse {
        if (! $this->canReact($actor, $role)) {
            return $this->reactionRedirect(
                request: $request,
                isCandidatePortal: $isCandidatePortal,
                candidateCompanySlug: $candidateCompanySlug,
                flashKey: 'error',
                flashMessage: __('social_hub.flash.reaction_forbidden')
            );
        }

        if ((string) $post->company_id !== $companyId) {
            $this->logPermissionWarning(
                actor: $actor,
                companyId: $companyId,
                role: $role,
                action: 'react_company_scope_mismatch',
                extra: ['post_id' => (string) $post->id, 'post_company_id' => (string) $post->company_id]
            );

            return $this->reactionRedirect(
                request: $request,
                isCandidatePortal: $isCandidatePortal,
                candidateCompanySlug: $candidateCompanySlug,
                flashKey: 'error',
                flashMessage: __('social_hub.flash.reaction_forbidden')
            );
        }

        if (
            $isCandidatePortal
            && (
                (string) $post->visibility !== SocialPost::VISIBILITY_PUBLIC
                || ! in_array((string) $post->type, $this->candidateVisiblePostTypes(), true)
            )
        ) {
            return $this->reactionRedirect(
                request: $request,
                isCandidatePortal: true,
                candidateCompanySlug: $candidateCompanySlug,
                flashKey: 'error',
                flashMessage: __('social_hub.flash.reaction_forbidden')
            );
        }

        if ((string) $post->author_user_id === (string) $actor->id) {
            $this->logPermissionWarning(
                actor: $actor,
                companyId: $companyId,
                role: $role,
                action: 'react_own_post_forbidden',
                extra: ['post_id' => (string) $post->id]
            );

            return $this->reactionRedirect(
                request: $request,
                isCandidatePortal: $isCandidatePortal,
                candidateCompanySlug: $candidateCompanySlug,
                flashKey: 'error',
                flashMessage: __('social_hub.flash.self_reaction_forbidden')
            );
        }

        $validated = $request->validate([
            'reaction_type' => ['required', Rule::in(SocialReaction::types())],
        ]);

        $reaction = SocialReaction::withoutGlobalScopes()->firstOrCreate(
            [
                'company_id' => $companyId,
                'post_id' => (string) $post->id,
                'reaction_type' => (string) $validated['reaction_type'],
                'user_id' => (string) $actor->id,
            ],
            [
                'created_at' => now(),
            ]
        );

        $this->socialHubService->syncReactionSummary($post);

        return $this->reactionRedirect(
            request: $request,
            isCandidatePortal: $isCandidatePortal,
            candidateCompanySlug: $candidateCompanySlug,
            flashKey: 'status',
            flashMessage: $reaction->wasRecentlyCreated
                ? __('social_hub.flash.reaction_added')
                : __('social_hub.flash.reaction_unchanged')
        );
    }

    private function storePollVoteForContext(
        Request $request,
        User $actor,
        string $companyId,
        SocialPost $post,
        bool $isCandidatePortal,
        ?string $candidateCompanySlug
    ): RedirectResponse {
        if ((string) $post->company_id !== $companyId) {
            return $this->reactionRedirect(
                request: $request,
                isCandidatePortal: $isCandidatePortal,
                candidateCompanySlug: $candidateCompanySlug,
                flashKey: 'error',
                flashMessage: __('social_hub.flash.poll_forbidden')
            );
        }

        if (
            $isCandidatePortal
            && (
                (string) $post->visibility !== SocialPost::VISIBILITY_PUBLIC
                || ! in_array((string) $post->type, $this->candidateVisiblePostTypes(), true)
            )
        ) {
            return $this->reactionRedirect(
                request: $request,
                isCandidatePortal: true,
                candidateCompanySlug: $candidateCompanySlug,
                flashKey: 'error',
                flashMessage: __('social_hub.flash.poll_forbidden')
            );
        }

        if ((string) $post->type !== SocialPost::TYPE_IDEA || ! is_array($post->poll_options_json) || $post->poll_options_json === []) {
            return $this->reactionRedirect(
                request: $request,
                isCandidatePortal: $isCandidatePortal,
                candidateCompanySlug: $candidateCompanySlug,
                flashKey: 'error',
                flashMessage: __('social_hub.flash.poll_forbidden')
            );
        }

        $allowedOptions = collect($post->poll_options_json)
            ->pluck('key')
            ->map(static fn ($key): string => (string) $key)
            ->filter(static fn ($key): bool => $key !== '')
            ->values()
            ->all();

        $validated = $request->validate([
            'option_key' => ['required', Rule::in($allowedOptions)],
        ]);

        SocialPulsePollVote::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $companyId,
                'post_id' => (string) $post->id,
                'user_id' => (string) $actor->id,
            ],
            [
                'option_key' => (string) $validated['option_key'],
                'created_at' => now(),
            ]
        );

        return $this->reactionRedirect(
            request: $request,
            isCandidatePortal: $isCandidatePortal,
            candidateCompanySlug: $candidateCompanySlug,
            flashKey: 'status',
            flashMessage: __('social_hub.flash.poll_vote_saved')
        );
    }

    private function reactionRedirect(
        Request $request,
        bool $isCandidatePortal,
        ?string $candidateCompanySlug,
        string $flashKey,
        string $flashMessage
    ): RedirectResponse {
        if ($isCandidatePortal) {
            return redirect()
                ->route('candidate.social-hub.index', ['company' => $candidateCompanySlug])
                ->with($flashKey, $flashMessage);
        }

        return redirect()
            ->route('social-hub.index', $this->companyQuery($request))
            ->with($flashKey, $flashMessage);
    }

    private function renderHub(
        Request $request,
        User $actor,
        Company $company,
        ?string $role,
        bool $isCandidatePortal,
        Collection $companies,
        string $redirectRoute
    ): View {
        $filters = $this->validatedFilters($request, (string) $company->id);
        $candidateVisibleTypes = $this->candidateVisiblePostTypes();
        $allowedPostTypes = $isCandidatePortal ? [] : $this->allowedPostTypesFor($actor, $role);
        $canCompose = ! $isCandidatePortal && $allowedPostTypes !== [];
        $canSendKudos = ! $isCandidatePortal && $this->canCreatePostType($actor, $role, SocialPost::TYPE_KUDOS, null);

        $posts = SocialPost::withoutGlobalScopes()
            ->with([
                'author.profile',
                'relatedJob:id,title,status,company_id',
                'reactionEntries',
                'pollVotes',
            ])
            ->where('company_id', (string) $company->id)
            ->when(
                $isCandidatePortal,
                fn ($query) => $query
                    ->where('visibility', SocialPost::VISIBILITY_PUBLIC)
                    ->whereIn('type', $candidateVisibleTypes)
            )
            ->when(
                $filters['post_types'] !== [],
                fn ($query) => $query->whereIn('type', $filters['post_types'])
            )
            ->when(
                is_string($filters['author_user_id']) && $filters['author_user_id'] !== '',
                fn ($query) => $query->where('author_user_id', $filters['author_user_id'])
            )
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $posts->getCollection()->transform(function (SocialPost $post): SocialPost {
            $post->setAttribute(
                'reaction_summary',
                $this->socialHubService->normalizeReactionSummary(
                    is_array($post->reactions) ? $post->reactions : []
                )
            );
            $post->setAttribute('poll_summary', $this->pollSummaryForPost($post));
            return $post;
        });

        $kudosLeadershipInsights = [];
        $internalMobilitySuggestions = [];
        if (! $isCandidatePortal) {
            $kudosLeadershipInsights = $this->socialHubService->buildKudosLeadershipInsights(
                companyId: (string) $company->id,
                limit: 3
            );

            $internalMobilitySuggestions = $this->socialHubService->suggestInternalRolesForUser(
                companyId: (string) $company->id,
                user: $actor,
                limit: 3
            );
        }

        return view('social-hub.index', [
            'requiresCompanySelection' => false,
            'isCandidatePortal' => $isCandidatePortal,
            'company' => $company,
            'companies' => $companies,
            'posts' => $posts,
            'authors' => $this->authorOptions((string) $company->id),
            'jobsForLinking' => Job::withoutGlobalScopes()
                ->where('company_id', (string) $company->id)
                ->where('status', Job::STATUS_PUBLISHED)
                ->orderBy('title')
                ->get(['id', 'title']),
            'filters' => $filters,
            'postTypes' => $isCandidatePortal ? $candidateVisibleTypes : SocialPost::types(),
            'reactionTypes' => SocialReaction::types(),
            'allowedPostTypes' => $allowedPostTypes,
            'kudosCategories' => $this->kudosCategories(),
            'canCompose' => $canCompose,
            'canSendKudos' => $canSendKudos,
            'canVotePolls' => true,
            'pulseSummary' => $this->pulseSummary((string) $company->id, $role, $isCandidatePortal),
            'kudosLeadershipInsights' => $kudosLeadershipInsights,
            'internalMobilitySuggestions' => $internalMobilitySuggestions,
            'hubRedirectRoute' => $redirectRoute,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function candidateVisiblePostTypes(): array
    {
        return [
            SocialPost::TYPE_KUDOS,
            SocialPost::TYPE_WELCOME,
            SocialPost::TYPE_ANNOUNCEMENT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pollSummaryForPost(SocialPost $post): array
    {
        $options = collect($post->poll_options_json ?? [])
            ->map(static function ($option): array {
                return [
                    'key' => (string) data_get($option, 'key', ''),
                    'emoji' => (string) data_get($option, 'emoji', ''),
                    'label' => (string) data_get($option, 'label', ''),
                ];
            })
            ->filter(static fn (array $option): bool => $option['key'] !== '')
            ->values();

        if ($options->isEmpty()) {
            return [];
        }

        $counts = $post->pollVotes
            ->groupBy(static fn (SocialPulsePollVote $vote): string => (string) $vote->option_key)
            ->map(static fn (Collection $votes): int => $votes->count());

        $totalVotes = max(1, $counts->sum());

        return $options->map(static function (array $option) use ($counts, $totalVotes): array {
            $count = (int) ($counts->get($option['key']) ?? 0);
            return [
                'key' => $option['key'],
                'emoji' => $option['emoji'],
                'label' => $option['label'],
                'count' => $count,
                'percent' => (int) round(($count / $totalVotes) * 100),
            ];
        })->all();
    }

    /**
     * @return array<int, array{key: string, emoji: string, label: string}>
     */
    private function defaultPollOptions(): array
    {
        return [
            ['key' => 'high', 'emoji' => "\u{1F50B}", 'label' => __('social_hub.polls.options.high')],
            ['key' => 'medium', 'emoji' => "\u{26A1}", 'label' => __('social_hub.polls.options.medium')],
            ['key' => 'low', 'emoji' => "\u{1FAAB}", 'label' => __('social_hub.polls.options.low')],
        ];
    }

    /**
     * @return array<int, array{option_key: string, count: int}>
     */
    private function pulseSummary(string $companyId, ?string $role, bool $isCandidatePortal): array
    {
        if ($isCandidatePortal) {
            return [];
        }

        if (! in_array((string) $role, [
            CompanyMembership::ROLE_COMPANY_ADMIN,
            CompanyMembership::ROLE_MANAGER,
        ], true)) {
            return [];
        }

        $rows = SocialPulsePollVote::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereHas('post', static function ($query): void {
                $query->where('type', SocialPost::TYPE_IDEA);
            })
            ->selectRaw('option_key, COUNT(*) as aggregate_count')
            ->groupBy('option_key')
            ->orderBy('option_key')
            ->get();

        return $rows->map(static fn (SocialPulsePollVote $row): array => [
            'option_key' => (string) $row->option_key,
            'count' => (int) ($row->aggregate_count ?? 0),
        ])->values()->all();
    }

    /**
     * @return array{0: Candidate}
     */
    private function resolveCandidatePortalContext(Request $request, Company $company, User $actor): array
    {
        abort_unless($company->status === Company::STATUS_ACTIVE, 404);

        $membership = CompanyMembership::query()
            ->where('company_id', (string) $company->id)
            ->where('user_id', (string) $actor->id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->where('company_role', CompanyMembership::ROLE_CANDIDATE)
            ->first();

        abort_unless($membership instanceof CompanyMembership, 403);

        $candidate = Candidate::withoutGlobalScopes()
            ->where('company_id', (string) $company->id)
            ->where('user_id', (string) $actor->id)
            ->first();

        abort_unless($candidate instanceof Candidate, 403);

        session(['active_company_id' => (string) $company->id]);

        return [$candidate];
    }

    /**
     * @return array{0: ?string, 1: Collection<int, Company>}
     */
    private function resolveCompanyContext(Request $request): array
    {
        $user = $request->user();
        $companies = collect();

        if ($user instanceof User && $user->isSuperadmin()) {
            $companies = Company::query()
                ->where('status', Company::STATUS_ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $companyId = $this->managedCompanyId($request, false);
        if ($companyId !== null) {
            $exists = Company::query()
                ->where('id', $companyId)
                ->where('status', Company::STATUS_ACTIVE)
                ->exists();

            if (! $exists) {
                $companyId = null;
            }
        }

        return [$companyId, $companies];
    }

    /**
     * @return array{post_types: array<int, string>, author_user_id: ?string}
     */
    private function validatedFilters(Request $request, string $companyId): array
    {
        $validated = $request->validate([
            'post_types' => ['nullable', 'array'],
            'post_types.*' => ['string', Rule::in(SocialPost::types())],
            'author_user_id' => [
                'nullable',
                'uuid',
                Rule::exists('company_memberships', 'user_id')->where(
                    static function ($query) use ($companyId): void {
                        $query->where('company_id', $companyId)
                            ->where('membership_status', CompanyMembership::STATUS_ACTIVE);
                    }
                ),
            ],
        ]);

        return [
            'post_types' => collect($validated['post_types'] ?? [])->map(static fn ($type) => (string) $type)->values()->all(),
            'author_user_id' => isset($validated['author_user_id']) ? (string) $validated['author_user_id'] : null,
        ];
    }

    /**
     * @return Collection<int, array{id: string, name: string}>
     */
    private function authorOptions(string $companyId): Collection
    {
        return CompanyMembership::query()
            ->with('user.profile')
            ->where('company_id', $companyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->whereIn('company_role', [
                CompanyMembership::ROLE_COMPANY_ADMIN,
                CompanyMembership::ROLE_RECRUITER,
                CompanyMembership::ROLE_MANAGER,
                CompanyMembership::ROLE_EMPLOYEE,
                CompanyMembership::ROLE_CANDIDATE,
            ])
            ->get()
            ->map(static fn (CompanyMembership $membership): array => [
                'id' => (string) $membership->user_id,
                'name' => (string) ($membership->user?->profile?->full_name ?? $membership->user?->email ?? $membership->user_id),
            ])
            ->sortBy('name')
            ->values();
    }

    private function activeMembershipRole(User $user, string $companyId): ?string
    {
        if ($user->isSuperadmin()) {
            return null;
        }

        return $user->memberships()
            ->where('company_id', $companyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->value('company_role');
    }

    private function canAccessRecruitmentHub(User $user, ?string $role): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return in_array((string) $role, [
            CompanyMembership::ROLE_COMPANY_ADMIN,
            CompanyMembership::ROLE_RECRUITER,
            CompanyMembership::ROLE_MANAGER,
            CompanyMembership::ROLE_EMPLOYEE,
        ], true);
    }

    /**
     * @return array<int, string>
     */
    private function allowedPostTypesFor(User $user, ?string $role): array
    {
        if ($user->isSuperadmin()) {
            return [
                SocialPost::TYPE_ANNOUNCEMENT,
                SocialPost::TYPE_IDEA,
            ];
        }

        return match ((string) $role) {
            CompanyMembership::ROLE_COMPANY_ADMIN => [
                SocialPost::TYPE_ANNOUNCEMENT,
                SocialPost::TYPE_IDEA,
            ],
            CompanyMembership::ROLE_MANAGER => [
                SocialPost::TYPE_ANNOUNCEMENT,
                SocialPost::TYPE_IDEA,
            ],
            CompanyMembership::ROLE_RECRUITER => [
                SocialPost::TYPE_WELCOME,
                SocialPost::TYPE_ANNOUNCEMENT,
                SocialPost::TYPE_IDEA,
            ],
            CompanyMembership::ROLE_EMPLOYEE => [
                SocialPost::TYPE_KUDOS,
            ],
            default => [],
        };
    }

    private function canCreatePostType(User $user, ?string $role, string $type, ?string $relatedJobId): bool
    {
        if (! in_array($type, $this->allowedPostTypesFor($user, $role), true)) {
            return false;
        }

        if ($type === SocialPost::TYPE_ANNOUNCEMENT && $relatedJobId !== null) {
            return in_array((string) $role, [
                CompanyMembership::ROLE_COMPANY_ADMIN,
                CompanyMembership::ROLE_RECRUITER,
            ], true) || $user->isSuperadmin();
        }

        if ($type === SocialPost::TYPE_ANNOUNCEMENT && $relatedJobId === null) {
            return in_array((string) $role, [
                CompanyMembership::ROLE_COMPANY_ADMIN,
                CompanyMembership::ROLE_MANAGER,
            ], true) || $user->isSuperadmin();
        }

        if ($type === SocialPost::TYPE_WELCOME) {
            return (string) $role === CompanyMembership::ROLE_RECRUITER || $user->isSuperadmin();
        }

        if ($type === SocialPost::TYPE_KUDOS) {
            return (string) $role === CompanyMembership::ROLE_EMPLOYEE;
        }

        return true;
    }

    private function canReact(User $user, ?string $role): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return in_array((string) $role, [
            CompanyMembership::ROLE_COMPANY_ADMIN,
            CompanyMembership::ROLE_RECRUITER,
            CompanyMembership::ROLE_MANAGER,
            CompanyMembership::ROLE_EMPLOYEE,
            CompanyMembership::ROLE_CANDIDATE,
        ], true);
    }

    /**
     * @return array<string, array{icon: string, label: string}>
     */
    private function kudosCategories(): array
    {
        return [
            'mvp' => [
                'icon' => "\u{1F3C6}",
                'label' => __('social_hub.kudos_categories.mvp'),
            ],
            'innovator' => [
                'icon' => "\u{1F4A1}",
                'label' => __('social_hub.kudos_categories.innovator'),
            ],
            'helper' => [
                'icon' => "\u{1F91D}",
                'label' => __('social_hub.kudos_categories.helper'),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function logPermissionWarning(
        User $actor,
        string $companyId,
        ?string $role,
        string $action,
        array $extra = []
    ): void {
        Log::warning('Social hub permission denied.', array_merge([
            'actor_user_id' => (string) $actor->id,
            'company_id' => $companyId,
            'role' => $role,
            'action' => $action,
        ], $extra));
    }

    /**
     * @return array<string, string>
     */
    private function companyQuery(Request $request): array
    {
        $companyId = (string) $request->input('company_id', $request->query('company_id', ''));

        return $companyId !== '' ? ['company_id' => $companyId] : [];
    }
}



