
<x-shell-layout :title="__('social_hub.index.title').' | '.config('app.name')">
    <style>
        .social-hub-red {
            background:
                radial-gradient(circle at 10% -10%, rgba(239, 68, 68, 0.24), transparent 40%),
                radial-gradient(circle at 90% 0%, rgba(248, 113, 113, 0.22), transparent 36%),
                radial-gradient(circle at 80% 100%, rgba(220, 38, 38, 0.14), transparent 30%),
                linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 1.25rem;
            padding: 1rem;
        }

        .social-hub-red .social-hub-card {
            background: rgba(255, 255, 255, 0.76);
            border: 1px solid rgba(248, 113, 113, 0.34);
            box-shadow: 0 16px 34px -26px rgba(220, 38, 38, 0.62);
            backdrop-filter: blur(18px);
        }

        .social-hub-red .select2-container .select2-selection--single,
        .social-hub-red .select2-container .select2-selection--multiple {
            border-color: rgba(248, 113, 113, 0.52) !important;
        }

        .social-hub-red .select2-dropdown {
            border-color: rgba(239, 68, 68, 0.48) !important;
            box-shadow: 0 16px 36px rgba(220, 38, 38, 0.22) !important;
        }

        .social-hub-red .select2-container--default.select2-container--focus .select2-selection--single,
        .social-hub-red .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: rgba(220, 38, 38, 0.86) !important;
            box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.35) !important;
        }
    </style>

    @php
        $indexRouteName = $isCandidatePortal ? 'candidate.social-hub.index' : 'social-hub.index';
        $indexRouteParams = $isCandidatePortal
            ? ['company' => $company->slug]
            : array_filter(['company_id' => request('company_id')]);

        $reactionRouteName = $isCandidatePortal ? 'candidate.social-hub.reactions.store' : 'social-hub.reactions.store';
        $pollVoteRouteName = $isCandidatePortal ? 'candidate.social-hub.poll-votes.store' : 'social-hub.poll-votes.store';

        $composerTypes = collect($allowedPostTypes ?? [])->reject(
            static fn (string $postType): bool => $postType === \App\Models\SocialPost::TYPE_KUDOS
        )->values();
    @endphp

    <section class="social-hub-red space-y-5">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif
        @if($errors->any())
            <x-toast-alert type="warning">{{ $errors->first() }}</x-toast-alert>
        @endif

        @if($requiresCompanySelection)
            <x-glass-card class="social-hub-card">
                <x-empty-state :title="__('social_hub.company_required.title')" :message="__('social_hub.company_required.message')" />
            </x-glass-card>
        @else
            <x-glass-card class="social-hub-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="max-w-2xl">
                        <p class="text-xs uppercase tracking-[0.28em] text-danger-700">{{ __('social_hub.index.watercooler_label') }}</p>
                        <h1 class="panel-title mt-1 text-3xl font-semibold tracking-tight text-slate-900">{{ __('social_hub.index.heading') }}</h1>
                        <p class="mt-1 text-sm text-slate-700">{{ __('social_hub.index.subheading') }}</p>
                    </div>

                    @unless($isCandidatePortal)
                        <div class="flex flex-wrap items-center gap-2">
                            @if($canCompose && $composerTypes->isNotEmpty())
                                <x-modal id="social-hub-composer-modal" :title="__('social_hub.composer.title')">
                                    <x-slot:trigger>
                                        <button type="button" class="rounded-xl bg-danger-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-danger-700">
                                            {{ __('social_hub.composer.open') }}
                                        </button>
                                    </x-slot:trigger>

                                    <form method="POST" action="{{ route('social-hub.posts.store', array_filter(['company_id' => request('company_id')])) }}" class="space-y-4" x-data="{ type: '{{ old('type', $composerTypes->first() ?? '') }}', pollEnabled: {{ old('poll_enabled') ? 'true' : 'false' }} }">
                                        @csrf
                                        @if(auth()->user()?->isSuperadmin() && request('company_id'))
                                            <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                                        @endif
                                        <input type="hidden" name="mode" value="standard">

                                        <x-form-field :label="__('social_hub.composer.post_type')" name="type" required>
                                            <select x-model="type" name="type" required data-placeholder="{{ __('social_hub.composer.post_type_placeholder') }}" class="w-full rounded-xl border border-danger-200/70 bg-white/90 px-3 py-2.5 text-sm text-slate-900">
                                                <option value="">{{ __('social_hub.composer.post_type_placeholder') }}</option>
                                                @foreach($composerTypes as $postType)
                                                    <option value="{{ $postType }}" @selected((string) old('type') === (string) $postType)>
                                                        {{ __('social_hub.post_types.'.$postType) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </x-form-field>

                                        <x-form-field :label="__('social_hub.composer.visibility')" name="visibility" required>
                                            <select name="visibility" required data-placeholder="{{ __('social_hub.composer.visibility_placeholder') }}" class="w-full rounded-xl border border-danger-200/70 bg-white/90 px-3 py-2.5 text-sm text-slate-900">
                                                @foreach(\App\Models\SocialPost::visibilities() as $visibility)
                                                    <option value="{{ $visibility }}" @selected((string) old('visibility', \App\Models\SocialPost::VISIBILITY_TEAM_ONLY) === (string) $visibility)>
                                                        {{ __('social_hub.visibilities.'.$visibility) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </x-form-field>

                                        <x-form-field :label="__('social_hub.composer.content')" name="content_text" :help="__('social_hub.composer.content_hint', ['max' => \App\Models\SocialPost::CONTENT_MAX_LENGTH])" required>
                                            <textarea name="content_text" rows="5" maxlength="{{ \App\Models\SocialPost::CONTENT_MAX_LENGTH }}" class="w-full rounded-xl border border-danger-200/70 bg-white/90 px-3 py-2.5 text-sm text-slate-900" placeholder="{{ __('social_hub.composer.content_placeholder') }}">{{ old('content_text') }}</textarea>
                                        </x-form-field>

                                        <x-form-field :label="__('social_hub.composer.media_url')" name="media_url" :help="__('social_hub.composer.media_hint')">
                                            <input type="url" name="media_url" value="{{ old('media_url') }}" class="w-full rounded-xl border border-danger-200/70 bg-white/90 px-3 py-2.5 text-sm text-slate-900" placeholder="https://">
                                        </x-form-field>

                                        <x-form-field :label="__('social_hub.composer.related_job')" name="related_job_id" :help="__('social_hub.composer.related_job_hint')">
                                            <select name="related_job_id" data-placeholder="{{ __('social_hub.composer.related_job_placeholder') }}" class="w-full rounded-xl border border-danger-200/70 bg-white/90 px-3 py-2.5 text-sm text-slate-900">
                                                <option value="">{{ __('social_hub.composer.related_job_placeholder') }}</option>
                                                @foreach($jobsForLinking as $linkableJob)
                                                    <option value="{{ $linkableJob->id }}" @selected((string) old('related_job_id') === (string) $linkableJob->id)>{{ $linkableJob->title }}</option>
                                                @endforeach
                                            </select>
                                        </x-form-field>

                                        <div class="rounded-xl border border-danger-200/60 bg-danger-50/65 p-3" x-show="type === '{{ \App\Models\SocialPost::TYPE_IDEA }}'">
                                            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-800">
                                                <input type="checkbox" name="poll_enabled" value="1" x-model="pollEnabled" class="rounded border-danger-300 text-danger-600 focus:ring-danger-400">
                                                <span>{{ __('social_hub.polls.enable_label') }}</span>
                                            </label>

                                            <div class="mt-2" x-show="pollEnabled">
                                                <x-form-field :label="__('social_hub.polls.question')" name="poll_question_text">
                                                    <input type="text" name="poll_question_text" maxlength="255" value="{{ old('poll_question_text') }}" class="w-full rounded-xl border border-danger-200/70 bg-white/90 px-3 py-2.5 text-sm text-slate-900" placeholder="{{ __('social_hub.polls.question_placeholder') }}">
                                                </x-form-field>
                                            </div>
                                        </div>

                                        <div class="flex justify-end gap-2">
                                            <button type="submit" class="rounded-xl bg-danger-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-danger-700">
                                                {{ __('social_hub.composer.submit') }}
                                            </button>
                                        </div>
                                    </form>
                                </x-modal>
                            @endif

                            @if($canSendKudos)
                                <x-modal id="social-hub-kudos-modal" :title="__('social_hub.kudos.modal_title')">
                                    <x-slot:trigger>
                                        <button type="button" class="rounded-xl border border-danger-300/70 bg-white px-4 py-2.5 text-sm font-semibold text-danger-700 transition-weightless hover:bg-danger-50">
                                            {{ __('social_hub.kudos.open') }}
                                        </button>
                                    </x-slot:trigger>

                                    <form method="POST" action="{{ route('social-hub.posts.store', array_filter(['company_id' => request('company_id')])) }}" class="space-y-4">
                                        @csrf
                                        @if(auth()->user()?->isSuperadmin() && request('company_id'))
                                            <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                                        @endif
                                        <input type="hidden" name="mode" value="kudos">
                                        <input type="hidden" name="type" value="{{ \App\Models\SocialPost::TYPE_KUDOS }}">

                                        <x-form-field :label="__('social_hub.composer.visibility')" name="visibility" required>
                                            <select name="visibility" required data-placeholder="{{ __('social_hub.composer.visibility_placeholder') }}" class="w-full rounded-xl border border-danger-200/70 bg-white/90 px-3 py-2.5 text-sm text-slate-900">
                                                @foreach(\App\Models\SocialPost::visibilities() as $visibility)
                                                    <option value="{{ $visibility }}" @selected((string) old('visibility', \App\Models\SocialPost::VISIBILITY_TEAM_ONLY) === (string) $visibility)>
                                                        {{ __('social_hub.visibilities.'.$visibility) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </x-form-field>

                                        <x-form-field :label="__('social_hub.kudos.recipient')" name="kudos_recipient_user_id" required>
                                            <select name="kudos_recipient_user_id" required data-placeholder="{{ __('social_hub.kudos.recipient_placeholder') }}" class="w-full rounded-xl border border-danger-200/70 bg-white/90 px-3 py-2.5 text-sm text-slate-900">
                                                <option value="">{{ __('social_hub.kudos.recipient_placeholder') }}</option>
                                                @foreach($authors as $author)
                                                    @continue((string) $author['id'] === (string) auth()->id())
                                                    <option value="{{ $author['id'] }}" @selected((string) old('kudos_recipient_user_id') === (string) $author['id'])>{{ $author['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </x-form-field>

                                        <x-form-field :label="__('social_hub.kudos.category')" name="kudos_category" required>
                                            <select name="kudos_category" required data-placeholder="{{ __('social_hub.kudos.category_placeholder') }}" class="w-full rounded-xl border border-danger-200/70 bg-white/90 px-3 py-2.5 text-sm text-slate-900">
                                                <option value="">{{ __('social_hub.kudos.category_placeholder') }}</option>
                                                @foreach($kudosCategories as $categoryKey => $category)
                                                    <option value="{{ $categoryKey }}" @selected((string) old('kudos_category') === (string) $categoryKey)>{{ $category['icon'] }} {{ $category['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </x-form-field>

                                        <x-form-field :label="__('social_hub.kudos.message')" name="kudos_message" required>
                                            <textarea name="kudos_message" rows="4" maxlength="{{ \App\Models\SocialPost::CONTENT_MAX_LENGTH }}" class="w-full rounded-xl border border-danger-200/70 bg-white/90 px-3 py-2.5 text-sm text-slate-900" placeholder="{{ __('social_hub.kudos.message_placeholder') }}">{{ old('kudos_message') }}</textarea>
                                        </x-form-field>

                                        <div class="flex justify-end gap-2">
                                            <button type="submit" class="rounded-xl bg-danger-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-danger-700">
                                                {{ __('social_hub.kudos.submit') }}
                                            </button>
                                        </div>
                                    </form>
                                </x-modal>
                            @endif

                            @if(! $canCompose && ! $canSendKudos)
                                <p class="max-w-xs rounded-xl border border-danger-200 bg-danger-50 px-3 py-2 text-xs text-danger-800">
                                    {{ __('social_hub.composer.no_permission') }}
                                </p>
                            @endif
                        </div>
                    @endunless
                </div>
            </x-glass-card>

            <x-glass-card class="social-hub-card p-4">
                <form method="GET" action="{{ route($indexRouteName, $isCandidatePortal ? ['company' => $company->slug] : []) }}" class="grid gap-3 md:grid-cols-4">
                    @if(auth()->user()?->isSuperadmin() && ! $isCandidatePortal)
                        <x-form-field :label="__('jobs.company')" name="company_id">
                            <select name="company_id" data-placeholder="{{ __('jobs.company_placeholder') }}" class="w-full rounded-xl border border-danger-200/70 bg-white/90 px-3 py-2 text-sm">
                                <option value="">{{ __('jobs.company_placeholder') }}</option>
                                @foreach($companies as $filterCompany)
                                    <option value="{{ $filterCompany->id }}" @selected((string) request('company_id') === (string) $filterCompany->id)>{{ $filterCompany->name }}</option>
                                @endforeach
                            </select>
                        </x-form-field>
                    @endif

                    <x-form-field :label="__('social_hub.filters.post_types')" name="post_types">
                        <select name="post_types[]" multiple data-placeholder="{{ __('social_hub.filters.post_types_placeholder') }}" class="w-full rounded-xl border border-danger-200/70 bg-white/90 px-3 py-2 text-sm">
                            @foreach($postTypes as $postType)
                                <option value="{{ $postType }}" @selected(in_array($postType, $filters['post_types'] ?? [], true))>
                                    {{ __('social_hub.post_types.'.$postType) }}
                                </option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field :label="__('social_hub.filters.author')" name="author_user_id">
                        <select name="author_user_id" data-placeholder="{{ __('social_hub.filters.author_placeholder') }}" class="w-full rounded-xl border border-danger-200/70 bg-white/90 px-3 py-2 text-sm">
                            <option value="">{{ __('social_hub.filters.author_placeholder') }}</option>
                            @foreach($authors as $author)
                                <option value="{{ $author['id'] }}" @selected((string) ($filters['author_user_id'] ?? '') === (string) $author['id'])>{{ $author['name'] }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-xl bg-danger-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-danger-700">
                            {{ __('social_hub.filters.apply') }}
                        </button>
                        <a href="{{ route($indexRouteName, $indexRouteParams) }}" class="rounded-xl border border-danger-300/70 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-weightless hover:bg-danger-50">
                            {{ __('social_hub.filters.reset') }}
                        </a>
                    </div>
                </form>
            </x-glass-card>

            @if(! empty($pulseSummary))
                <x-glass-card class="social-hub-card p-4">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-danger-700">{{ __('social_hub.polls.pulse_heading') }}</h2>
                    <p class="mt-1 text-xs text-slate-600">{{ __('social_hub.polls.pulse_subheading') }}</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                        @foreach($pulseSummary as $pulseItem)
                            <div class="rounded-xl border border-danger-200/60 bg-white/85 px-3 py-2">
                                <p class="text-xs uppercase tracking-[0.16em] text-danger-700">{{ __('social_hub.polls.options.'.$pulseItem['option_key']) }}</p>
                                <p class="mt-1 text-xl font-semibold text-slate-900">{{ $pulseItem['count'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </x-glass-card>
            @endif

            @if(! $isCandidatePortal && ! empty($kudosLeadershipInsights))
                <x-glass-card class="social-hub-card p-4">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-danger-700">{{ __('social_hub.ai.kudos_title') }}</h2>
                    <p class="mt-1 text-xs text-slate-600">{{ __('social_hub.ai.kudos_subheading') }}</p>
                    <div class="mt-3 grid gap-3 lg:grid-cols-3">
                        @foreach($kudosLeadershipInsights as $leader)
                            <div class="rounded-xl border border-danger-200/70 bg-white/85 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-900">{{ $leader['recipient_name'] }}</p>
                                    <span class="rounded-full border border-danger-200 bg-danger-50 px-2 py-0.5 text-xs font-semibold text-danger-800">
                                        {{ __('social_hub.ai.score_badge', ['score' => number_format((float) $leader['score'], 1)]) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-600">
                                    {{ __('social_hub.ai.kudos_count', ['count' => (int) $leader['kudos_count']]) }}
                                </p>
                                <p class="mt-1 text-xs text-slate-600">
                                    {{ __('social_hub.ai.leading_signal', ['signal' => __('social_hub.kudos_categories.'.(string) $leader['dominant_category_key'])]) }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </x-glass-card>
            @endif

            @if(! $isCandidatePortal && ! empty($internalMobilitySuggestions))
                <x-glass-card class="social-hub-card p-4">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-danger-700">{{ __('social_hub.ai.mobility_title') }}</h2>
                    <p class="mt-1 text-xs text-slate-600">{{ __('social_hub.ai.mobility_subheading') }}</p>
                    <div class="mt-3 grid gap-3 lg:grid-cols-3">
                        @foreach($internalMobilitySuggestions as $suggestion)
                            <article class="rounded-xl border border-primary-200/70 bg-primary-50/60 p-3">
                                <p class="text-sm font-semibold text-slate-900">{{ $suggestion['job_title'] }}</p>
                                <p class="mt-1 text-xs text-slate-600">
                                    {{ __('social_hub.ai.match_score', ['score' => (int) $suggestion['match_score']]) }}
                                </p>
                                <p class="mt-1 text-xs text-slate-600">
                                    {{ __('social_hub.ai.match_signals', [
                                        'signals' => collect((array) ($suggestion['matching_signals'] ?? []))
                                            ->map(static fn (string $signal): string => __('social_hub.ai.signal_labels.'.$signal))
                                            ->implode(', '),
                                    ]) }}
                                </p>
                                <a href="{{ route('public.jobs.show', ['job' => $suggestion['job_id']]) }}"
                                   class="mt-3 inline-flex rounded-lg border border-primary-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-primary-800 transition-weightless hover:bg-primary-100">
                                    {{ __('social_hub.feed.view_roles') }}
                                </a>
                            </article>
                        @endforeach
                    </div>
                </x-glass-card>
            @endif

            @if($posts->isEmpty())
                <x-glass-card class="social-hub-card">
                    <x-empty-state :title="__('social_hub.index.empty_title')" :message="__('social_hub.index.empty_message')" />
                </x-glass-card>
            @else
                <div class="space-y-4">
                    @foreach($posts as $post)
                        @php
                            $typeClass = match ((string) $post->type) {
                                \App\Models\SocialPost::TYPE_ANNOUNCEMENT => 'bg-danger-100 text-danger-900 border-danger-200',
                                \App\Models\SocialPost::TYPE_WELCOME => 'bg-success-100 text-success-900 border-success-200',
                                \App\Models\SocialPost::TYPE_IDEA => 'bg-primary-100 text-primary-900 border-primary-200',
                                default => 'bg-slate-100 text-slate-900 border-slate-200',
                            };
                            $isOwnPost = (string) $post->author_user_id === (string) auth()->id();
                            $metadata = is_array($post->metadata_json) ? $post->metadata_json : [];
                            $kudosMeta = is_array(data_get($metadata, 'kudos')) ? data_get($metadata, 'kudos') : [];
                            $interactionMeta = is_array(data_get($metadata, 'interaction')) ? data_get($metadata, 'interaction') : [];
                            $reactionSummary = is_array($post->reaction_summary ?? null) ? $post->reaction_summary : [];
                        @endphp

                        <x-glass-card class="social-hub-card p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ __('social_hub.feed.posted_by', ['name' => $post->author?->profile?->full_name ?? $post->author?->email ?? __('social_hub.unknown_author')]) }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-600">{{ optional($post->created_at)->diffForHumans() }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $typeClass }}">
                                        {{ __('social_hub.post_types.'.$post->type) }}
                                    </span>
                                    <span class="rounded-full border border-slate-200 bg-white/85 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                        {{ __('social_hub.visibilities.'.$post->visibility) }}
                                    </span>
                                </div>
                            </div>

                            @if(! empty($kudosMeta))
                                <div class="mt-3 rounded-xl border border-success-200/80 bg-success-50/75 px-3 py-2 text-sm text-slate-800">
                                    <p class="font-semibold text-success-900">{{ data_get($kudosMeta, 'icon') }} {{ data_get($kudosMeta, 'category_label') }}</p>
                                    <p class="mt-1 text-xs text-slate-700">
                                        {{ __('social_hub.kudos.from_to', [
                                            'sender' => $post->author?->profile?->full_name ?? $post->author?->email ?? __('social_hub.unknown_author'),
                                            'recipient' => (string) data_get($kudosMeta, 'recipient_name', __('social_hub.unknown_author')),
                                        ]) }}
                                    </p>
                                </div>
                            @endif

                            <div class="mt-3 space-y-2">
                                <p class="text-sm leading-relaxed text-slate-800">{!! nl2br(e((string) $post->content_text)) !!}</p>

                                @if($post->media_url)
                                    <a href="{{ $post->media_url }}" target="_blank" rel="noopener" class="inline-flex rounded-lg border border-danger-200 bg-danger-50 px-2.5 py-1.5 text-xs font-semibold text-danger-800 transition-weightless hover:bg-danger-100">
                                        {{ __('social_hub.feed.media_link') }}
                                    </a>
                                @endif

                                @if($post->relatedJob)
                                    @php
                                        $ctaLabel = (string) data_get($metadata, 'cta.label', __('social_hub.feed.view_roles'));
                                    @endphp
                                    <a href="{{ route('public.jobs.show', ['job' => $post->relatedJob]) }}" class="inline-flex rounded-lg border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-800 transition-weightless hover:bg-primary-100">
                                        {{ $ctaLabel }}
                                    </a>
                                @endif
                            </div>

                            @if((string) $post->type === \App\Models\SocialPost::TYPE_IDEA && ! empty($post->poll_summary))
                                <div class="mt-4 rounded-xl border border-primary-200/70 bg-primary-50/65 p-3">
                                    <p class="text-xs uppercase tracking-[0.18em] text-primary-700">{{ __('social_hub.polls.label') }}</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $post->poll_question_text }}</p>

                                    @if($canVotePolls)
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach($post->poll_summary as $pollOption)
                                                <form method="POST" action="{{ route($pollVoteRouteName, $isCandidatePortal ? ['company' => $company->slug, 'post' => $post->id] : array_filter(['post' => $post->id, 'company_id' => request('company_id')])) }}">
                                                    @csrf
                                                    @if(auth()->user()?->isSuperadmin() && request('company_id') && ! $isCandidatePortal)
                                                        <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                                                    @endif
                                                    <input type="hidden" name="option_key" value="{{ $pollOption['key'] }}">
                                                    <button type="submit" class="rounded-full border border-primary-300/70 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 transition-weightless hover:bg-primary-100">
                                                        {{ $pollOption['emoji'] }} {{ $pollOption['label'] }}
                                                    </button>
                                                </form>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="mt-3 space-y-2">
                                        @foreach($post->poll_summary as $pollOption)
                                            <div>
                                                <div class="flex items-center justify-between text-xs text-slate-700">
                                                    <span>{{ $pollOption['emoji'] }} {{ $pollOption['label'] }}</span>
                                                    <span>{{ $pollOption['count'] }} ({{ $pollOption['percent'] }}%)</span>
                                                </div>
                                                <div class="mt-1 h-2 rounded-full bg-white/80">
                                                    <div class="h-2 rounded-full bg-primary-500" style="width: {{ $pollOption['percent'] }}%"></div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach($reactionTypes as $reactionType)
                                    @php
                                        $count = (int) ($reactionSummary[$reactionType] ?? 0);
                                        $hasReacted = $post->reactionEntries->contains(
                                            fn ($reaction) => (string) $reaction->reaction_type === (string) $reactionType
                                                && (string) $reaction->user_id === (string) auth()->id()
                                        );
                                    @endphp

                                    @if($isOwnPost)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-500">
                                            <span>{{ $reactionType }}</span>
                                            <span class="rounded-full bg-white px-1.5 py-0.5 text-[11px]">{{ $count }}</span>
                                        </span>
                                    @else
                                        <form method="POST" action="{{ route($reactionRouteName, $isCandidatePortal ? ['company' => $company->slug, 'post' => $post->id] : array_filter(['post' => $post->id, 'company_id' => request('company_id')])) }}">
                                            @csrf
                                            @if(auth()->user()?->isSuperadmin() && request('company_id') && ! $isCandidatePortal)
                                                <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                                            @endif
                                            <input type="hidden" name="reaction_type" value="{{ $reactionType }}">
                                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition-weightless {{ $hasReacted ? 'border-danger-300 bg-danger-100 text-danger-900' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                                                <span>{{ $reactionType }}</span>
                                                <span class="rounded-full bg-white/80 px-1.5 py-0.5 text-[11px]">{{ $count }}</span>
                                            </button>
                                        </form>
                                    @endif
                                @endforeach
                            </div>

                            @if(! $isOwnPost && (string) data_get($interactionMeta, 'label', '') !== '')
                                <form method="POST" action="{{ route($reactionRouteName, $isCandidatePortal ? ['company' => $company->slug, 'post' => $post->id] : array_filter(['post' => $post->id, 'company_id' => request('company_id')])) }}" class="mt-3">
                                    @csrf
                                    @if(auth()->user()?->isSuperadmin() && request('company_id') && ! $isCandidatePortal)
                                        <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                                    @endif
                                    <input type="hidden" name="reaction_type" value="{{ (string) data_get($interactionMeta, 'reaction', \App\Models\SocialReaction::TYPE_WAVE) }}">
                                    <button type="submit" class="rounded-xl border border-danger-300 bg-danger-50 px-3 py-1.5 text-xs font-semibold text-danger-800 transition-weightless hover:bg-danger-100">
                                        {{ (string) data_get($interactionMeta, 'label') }}
                                    </button>
                                </form>
                            @endif

                            @if($isOwnPost)
                                <p class="mt-2 text-xs text-slate-500">{{ __('social_hub.reactions.own_post_hint') }}</p>
                            @endif
                        </x-glass-card>
                    @endforeach
                </div>

                @if($posts instanceof \Illuminate\Contracts\Pagination\Paginator)
                    <div>{{ $posts->links() }}</div>
                @endif
            @endif
        @endif
    </section>
</x-shell-layout>
