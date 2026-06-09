@if(($values ?? collect())->isNotEmpty())
    <style>
        @keyframes culture-card-rise {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes culture-card-float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-3px);
            }
        }

        .culture-glass-card {
            animation:
                culture-card-rise 620ms ease-out both,
                culture-card-float 8s ease-in-out infinite;
            animation-delay: var(--culture-delay, 0ms), calc(var(--culture-delay, 0ms) + 620ms);
            background:
                radial-gradient(circle at 15% 20%, rgba(99, 102, 241, 0.14), transparent 48%),
                radial-gradient(circle at 85% 0%, rgba(34, 197, 94, 0.14), transparent 38%),
                linear-gradient(145deg, rgba(255, 255, 255, 0.82), rgba(255, 255, 255, 0.62));
        }
    </style>

    <x-glass-card
        :title="__('candidate_portal.culture.title')"
        :subtitle="__('candidate_portal.culture.subtitle')">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" data-culture-values-module>
            @foreach($values as $index => $value)
                <article class="culture-glass-card rounded-2xl border border-white/75 p-5 shadow-[0_24px_65px_-46px_rgba(59,130,246,0.5)] backdrop-blur-2xl transition-weightless hover:-translate-y-1 hover:shadow-[0_28px_72px_-44px_rgba(37,99,235,0.58)]"
                         style="--culture-delay: {{ $index * 120 }}ms;"
                         data-culture-value-card>
                    <p class="text-xs uppercase tracking-[0.22em] text-aura-700/85">
                        {{ __('candidate_portal.culture.pillar_label') }}
                    </p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900">
                        {{ $value->title }}
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-700">
                        {{ $value->description ?: __('public_site.common.not_available') }}
                    </p>

                    @if($value->icon_name)
                        <p class="mt-3 text-[11px] uppercase tracking-[0.18em] text-aura-800/80">
                            {{ $value->icon_name }}
                        </p>
                    @endif
                </article>
            @endforeach
        </div>
    </x-glass-card>
@endif
