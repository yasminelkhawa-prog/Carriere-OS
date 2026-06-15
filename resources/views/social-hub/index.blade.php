<x-shell-layout :title="__('social_hub.index.title').' | '.config('app.name')">
    <style>
        .social-hub-container {
            width: 100%;
            padding: 0.5rem 0;
        }

        .social-hub-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
        }

        .tab-pill {
            border-radius: 9999px;
            padding: 0.375rem 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            transition-property: all;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms;
        }

        .tab-pill-active {
            background-color: #f1f5f9;
            color: #0f172a;
            border: 1px solid #cbd5e1;
        }

        .tab-pill-inactive {
            background-color: #ffffff;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .tab-pill-inactive:hover {
            background-color: #f8fafc;
            color: #0f172a;
        }
    </style>

    @php
        $indexRouteName = $isCandidatePortal ? 'candidate.social-hub.index' : 'social-hub.index';
        $indexRouteParams = $isCandidatePortal
            ? ['company' => $company->slug]
            : array_filter(['company_id' => request('company_id')]);

        $reactionRouteName = $isCandidatePortal ? 'candidate.social-hub.reactions.store' : 'social-hub.reactions.store';

        $currentTab = request('tab');
        $tabs = [
            '' => 'Tous',
            'welcome' => 'Bienvenue',
            'announcement' => 'Annonce RH',
            'event' => 'Événement',
            'idea' => 'Conseil',
            'job' => 'Job',
            'kudos' => 'Félicitations',
            'media' => 'Média',
        ];
    @endphp

    <section class="social-hub-container">
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
            <div class="social-hub-card p-5">
                <x-empty-state :title="__('social_hub.company_required.title')" :message="__('social_hub.company_required.message')" />
            </div>
        @else
            <div class="flex flex-wrap items-center justify-end gap-4 mb-3">

                @unless($isCandidatePortal)
                    <div class="flex items-center gap-2">
                        @if($canCompose)
                            <x-modal id="social-hub-composer-modal" title="Créer un nouveau message de bienvenue">
                                <x-slot:trigger>
                                    <button type="button" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-slate-800 transition-all">
                                        + Nouveau
                                    </button>
                                </x-slot:trigger>

                                <form method="POST" action="{{ route('social-hub.posts.store', array_filter(['company_id' => request('company_id')])) }}" enctype="multipart/form-data" class="space-y-4">
                                    @csrf
                                    @if(auth()->user()?->isSuperadmin() && request('company_id'))
                                        <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                                    @endif

                                    <x-form-field :label="__('social_hub.composer.visibility')" name="visibility" required>
                                        <select name="visibility" required class="w-full rounded-xl border border-slate-200 bg-white/90 px-3 py-2.5 text-sm text-slate-900">
                                            @foreach(\App\Models\SocialPost::visibilities() as $visibility)
                                                <option value="{{ $visibility }}" @selected((string) old('visibility', \App\Models\SocialPost::VISIBILITY_TEAM_ONLY) === (string) $visibility)>
                                                    {{ __('social_hub.visibilities.'.$visibility) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </x-form-field>

                                    <x-form-field :label="__('social_hub.composer.content')" name="content_text" required>
                                        <textarea name="content_text" rows="5" maxlength="{{ \App\Models\SocialPost::CONTENT_MAX_LENGTH }}" class="w-full rounded-xl border border-slate-200 bg-white/90 px-3 py-2.5 text-sm text-slate-900" placeholder="Présentez le nouveau collaborateur... (ex: Lilly\n\nNous sommes ravis d'accueillir...)">{{ old('content_text') }}</textarea>
                                    </x-form-field>

                                    <x-form-field label="Image (optionnelle)" name="media">
                                        <input type="file" name="media" accept="image/*" class="w-full rounded-xl border border-slate-200 bg-white/90 px-3 py-2 text-sm text-slate-900 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-slate-50 file:text-slate-700 hover:file:bg-slate-100">
                                    </x-form-field>

                                    <div class="flex justify-end gap-2">
                                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition-all hover:bg-slate-800">
                                            Publier
                                        </button>
                                    </div>
                                </form>
                            </x-modal>
                        @endif
                    </div>
                @endunless
            </div>

            <!-- Posts List -->
            @if($posts->isEmpty())
                <div class="social-hub-card p-8 text-center text-slate-500">
                    <x-empty-state :title="__('social_hub.index.empty_title')" :message="__('social_hub.index.empty_message')" />
                </div>
            @else
                <div class="space-y-4">
                    @foreach($posts as $post)
                        @php
                            $authorName = $post->author?->profile?->full_name ?? $post->author?->email ?? __('social_hub.unknown_author');
                            $initials = collect(explode(' ', trim($authorName)))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
                            $avatarUrl = $post->author?->profile?->avatar_url ?? 'https://api.dicebear.com/9.x/avataaars/svg?seed=' . urlencode($authorName) . '&backgroundColor=e2e8f0';


                            $typeLabel = match ((string) $post->type) {
                                \App\Models\SocialPost::TYPE_ANNOUNCEMENT => 'Annonce RH',
                                \App\Models\SocialPost::TYPE_WELCOME => 'Bienvenue',
                                \App\Models\SocialPost::TYPE_IDEA => 'Conseil',
                                \App\Models\SocialPost::TYPE_KUDOS => 'Félicitations',
                                'event' => 'Événement',
                                'job' => 'Job',
                                'media' => 'Média',
                                default => strtoupper($post->type),
                            };

                            $typeClass = match ((string) $post->type) {
                                \App\Models\SocialPost::TYPE_ANNOUNCEMENT => 'bg-blue-50 text-blue-600 border border-blue-100',
                                \App\Models\SocialPost::TYPE_WELCOME => 'bg-pink-50 text-pink-600 border border-pink-100',
                                \App\Models\SocialPost::TYPE_IDEA => 'bg-amber-50 text-amber-600 border border-amber-100',
                                \App\Models\SocialPost::TYPE_KUDOS => 'bg-yellow-50 text-yellow-600 border border-yellow-100',
                                default => 'bg-slate-50 text-slate-600 border border-slate-100',
                            };

                            $isOwnPost = (string) $post->author_user_id === (string) auth()->id();
                            $metadata = is_array($post->metadata_json) ? $post->metadata_json : [];
                            $reactionSummary = is_array($post->reaction_summary ?? null) ? $post->reaction_summary : [];
                            $comments = $metadata['comments'] ?? [];

                            // Split first line as title
                            $lines = explode("\n", trim($post->content_text));
                            $title = null;
                            $body = $post->content_text;
                            if (count($lines) > 1 && strlen($lines[0]) < 80) {
                                $title = $lines[0];
                                $body = implode("\n", array_slice($lines, 1));
                            }
                        @endphp

                        <div class="social-hub-card">
                            <!-- Post Header -->
                            <div class="flex items-center gap-3">
                                <div class="shrink-0">
                                    @if($avatarUrl)
                                        <img src="{{ $avatarUrl }}" alt="{{ $authorName }}" class="h-10 w-10 rounded-full object-cover">
                                    @else
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700">
                                            {{ $initials }}
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <h3 class="text-base font-semibold text-slate-900 leading-tight">{{ $authorName }}</h3>
                                    <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-500">
                                        <span>{{ optional($post->created_at)->diffForHumans() }}</span>
                                        <span>•</span>
                                        <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-bold uppercase {{ $typeClass }}">
                                            ✔ {{ $typeLabel }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Post Content -->
                            <div class="mt-4">
                                @if($title)
                                    <h4 class="text-base font-bold text-slate-900 mb-1.5">{{ $title }}</h4>
                                @endif
                                <p class="text-base text-slate-700 leading-relaxed font-normal">{!! nl2br(e(trim($body))) !!}</p>

                                <!-- Large Image Display -->
                                @if($post->media_url)
                                    <div class="mt-4 overflow-hidden rounded-xl border border-slate-100 bg-slate-50">
                                        <img src="{{ $post->media_url }}" alt="Post media" class="w-full h-auto object-cover max-h-[600px]">
                                    </div>
                                @endif
                            </div>

                            <!-- Action & Counter Row -->
                            <div class="mt-4 flex items-center justify-between border-t border-b border-slate-100 py-3">
                                <div class="flex items-center gap-4">
                                    @foreach($reactionTypes as $reactionType)
                                        @php
                                            $count = (int) ($reactionSummary[$reactionType] ?? 0);
                                            $hasReacted = $post->reactionEntries->contains(
                                                fn ($reaction) => (string) $reaction->reaction_type === (string) $reactionType
                                                    && (string) $reaction->user_id === (string) auth()->id()
                                            );
                                        @endphp
                                        @if($isOwnPost)
                                            <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 bg-slate-50 border border-slate-100 rounded-full px-3 py-1">
                                                <span>{{ $reactionType }}</span>
                                                <span>{{ $count }}</span>
                                            </span>
                                        @else
                                            <form method="POST" action="{{ route($reactionRouteName, $isCandidatePortal ? ['company' => $company->slug, 'post' => $post->id] : array_filter(['post' => $post->id, 'company_id' => request('company_id')])) }}" class="inline">
                                                @csrf
                                                @if(auth()->user()?->isSuperadmin() && request('company_id') && ! $isCandidatePortal)
                                                    <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                                                @endif
                                                <input type="hidden" name="reaction_type" value="{{ $reactionType }}">
                                                <button type="submit" class="inline-flex items-center gap-1.5 text-xs rounded-full border px-3 py-1 transition-all {{ $hasReacted ? 'border-pink-200 bg-pink-50 text-pink-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                                                    <span>{{ $reactionType }}</span>
                                                    <span>{{ $count }}</span>
                                                </button>
                                            </form>
                                        @endif
                                    @endforeach

                                    <!-- Comment Count Indicator -->
                                    <button type="button" class="inline-flex items-center gap-1.5 text-xs text-slate-500 border border-slate-200 bg-white hover:bg-slate-50 rounded-full px-3 py-1 transition-all">
                                        <span>💬</span>
                                        <span>{{ count($comments) }}</span>
                                    </button>
                                </div>

                                <!-- Far Right: Reactor Names text -->
                                @php
                                    $reactors = $post->reactionEntries->map(function ($entry) {
                                        return $entry->user?->profile?->full_name ?? $entry->user?->email;
                                    })->filter()->unique()->values();

                                    $reactorText = '';
                                    if ($reactors->count() > 0) {
                                        $firstReactor = $reactors->first();
                                        if ($reactors->count() == 1) {
                                            $reactorText = "Aimé par " . e($firstReactor);
                                        } else {
                                            $reactorText = "Aimé par " . e($firstReactor) . " et " . ($reactors->count() - 1) . " autres";
                                        }
                                    }
                                @endphp
                                @if($reactorText !== '')
                                    <div class="text-xs text-slate-500 font-normal">
                                        {{ $reactorText }}
                                    </div>
                                @endif
                            </div>

                            <!-- Comments List Section (Flat) -->
                            @if(!empty($comments))
                                <div class="mt-4 space-y-4 pt-2">
                                    @foreach($comments as $comment)
                                        @php
                                            $commentTime = isset($comment['created_at']) ? \Carbon\Carbon::parse($comment['created_at']) : null;
                                            $commentDiff = $commentTime ? $commentTime->diffForHumans() : '';

                                            $commentAuthorName = $comment['user_name'] ?? 'Utilisateur';
                                            $commentInitials = collect(explode(' ', trim($commentAuthorName)))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');

                                            $commentUser = \App\Models\User::find($comment['user_id'] ?? null);
                                            $commentAvatarUrl = $commentUser?->profile?->avatar_url ?? 'https://api.dicebear.com/9.x/avataaars/svg?seed=' . urlencode($commentAuthorName) . '&backgroundColor=e2e8f0';

                                        @endphp
                                        <div class="flex items-start gap-3">
                                            <div class="shrink-0">
                                                @if($commentAvatarUrl)
                                                    <img src="{{ $commentAvatarUrl }}" alt="{{ $commentAuthorName }}" class="h-8 w-8 rounded-full object-cover">
                                                @else
                                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-[10px] font-semibold text-slate-700">
                                                        {{ $commentInitials }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-baseline justify-between gap-2">
                                                    <span class="text-xs font-bold text-slate-900">{{ $commentAuthorName }}</span>
                                                    <span class="text-[10px] text-slate-400">{{ $commentDiff }}</span>
                                                </div>
                                                <p class="mt-1 text-xs text-slate-700 leading-normal">{{ $comment['comment_text'] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Dynamic Comments Form -->
                            <form method="POST" action="{{ route($isCandidatePortal ? 'candidate.social-hub.comments.store' : 'social-hub.comments.store', $isCandidatePortal ? ['company' => $company->slug, 'post' => $post->id] : ['post' => $post->id]) }}" class="mt-4 flex gap-2">
                                @csrf
                                @if(auth()->user()?->isSuperadmin() && request('company_id') && !$isCandidatePortal)
                                    <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                                @endif
                                <input type="text" name="comment_text" placeholder="Votre commentaire..." required class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs focus:border-emerald-500 focus:ring-emerald-500">
                                <button type="submit" class="rounded-xl bg-[#004d3d] hover:bg-[#00382c] px-5 py-2.5 text-xs font-semibold text-white transition-all shadow-sm">Poster</button>
                            </form>
                        </div>
                    @endforeach
                </div>

                @if($posts instanceof \Illuminate\Contracts\Pagination\Paginator)
                    <div class="mt-4">{{ $posts->links() }}</div>
                @endif
            @endif
        @endif
    </section>
</x-shell-layout>
