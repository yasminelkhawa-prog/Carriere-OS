@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-center gap-2 mt-8 w-full">
        
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-white/50 border border-slate-200 text-slate-400 cursor-not-allowed shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-aura-600 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
        @endif

        {{-- Pagination Elements --}}
        <div class="flex items-center gap-1">
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <span class="flex items-center justify-center w-10 h-10 text-slate-500 font-medium">...</span>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-aura-600 text-white font-semibold shadow-sm shadow-aura-600/30">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" class="flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-700 font-medium hover:bg-slate-50 hover:text-aura-600 transition-colors shadow-sm">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-aura-600 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        @else
            <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-white/50 border border-slate-200 text-slate-400 cursor-not-allowed shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </span>
        @endif

    </nav>
@endif
