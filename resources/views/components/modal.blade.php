@props([
    'id' => null,
    'title',
    'maxWidth' => '2xl',
])

<div
    x-data="{
        open: false
    }"
    class="js-modal"
>
    <div @click="open = true">
        {{ $trigger }}
    </div>

    <template x-teleport="body">
        <div>
            <div x-cloak x-show="open" class="fixed inset-0 z-[190] bg-aura-900/20 backdrop-blur-sm transition-weightless" @click="open = false"></div>
            <div
                x-cloak
                x-show="open"
                x-transition
                class="fixed inset-0 z-[200] overflow-y-auto"
                @click.self="open = false"
            >
                <div class="flex min-h-full items-center justify-center p-4 sm:p-6 lg:p-8" @click.self="open = false">
                    <section
                        @if($id) id="{{ $id }}" @endif
                        class="mx-auto w-full max-w-{{ $maxWidth }} rounded-2xl border border-white/70 bg-white/85 p-6 shadow-aura backdrop-blur-2xl"
                    >
                        <header class="flex items-center justify-between gap-4">
                        <h3 class="text-lg font-semibold text-slate-900">{{ $title }}</h3>
                        <button type="button" class="rounded-lg border border-aura-300/50 bg-white/70 px-2.5 py-1 text-xs text-slate-800 transition-weightless hover:bg-white" @click.prevent="open = false; console.log('Closed clicked')">
                            {{ __('ui.nav.close') }}
                        </button>
                    </header>
                    <div class="mt-4" x-init="$nextTick(() => document.dispatchEvent(new CustomEvent('app:select2-refresh', { detail: { root: $el } })))">
                        {{ $slot }}
                    </div>
                </section>
                </div>
            </div>
        </div>
    </template>
</div>
