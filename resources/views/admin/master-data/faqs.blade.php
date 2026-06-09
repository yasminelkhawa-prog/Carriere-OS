<x-shell-layout :title="__('master.faqs.title').' | '.config('app.name')">
    <x-glass-card :title="__('master.faqs.title')" :subtitle="__('master.faqs.subtitle')">
        @if (session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif

        <form method="GET" action="{{ route('admin.faqs.index') }}" class="mt-4 grid gap-3 md:grid-cols-4">
            @if(auth()->user()?->isSuperadmin())
                <x-form-field :label="__('master.company_filter.label')" name="company_id">
                    <select name="company_id" data-placeholder="{{ __('master.company_filter.placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                        <option value="">{{ __('master.company_filter.placeholder') }}</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </x-form-field>
            @endif
            <x-form-field :label="__('master.faqs.category')" name="category">
                <select name="category" data-placeholder="{{ __('master.faqs.category_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                    <option value="">{{ __('master.faqs.category_placeholder') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected($selectedCategory === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </x-form-field>
            <x-form-field :label="__('master.faqs.search')" name="q">
                <input type="text" name="q" value="{{ $searchTerm }}" placeholder="{{ __('master.faqs.search_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
            </x-form-field>
            <div class="flex items-end">
                <button type="submit" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">{{ __('master.faqs.filters_apply') }}</button>
            </div>
        </form>

        @if($requiresCompanySelection)
            <div class="mt-4">
                <x-empty-state :title="__('master.company_filter.label')" :message="__('master.common.company_scope_required')" />
            </div>
        @else
            <form method="POST" action="{{ route('admin.faqs.store') }}" class="mt-5 grid gap-3">
                @csrf
                @if(auth()->user()?->isSuperadmin())
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                @endif
                <div class="grid gap-3 md:grid-cols-2">
                    <x-form-field :label="__('master.faqs.category')" name="category">
                        <input type="text" name="category" value="{{ old('category') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                    </x-form-field>
                    <x-form-field :label="__('master.faqs.question')" name="question">
                        <input type="text" name="question" value="{{ old('question') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                    </x-form-field>
                </div>
                <x-form-field :label="__('master.faqs.answer')" name="answer">
                    <textarea name="answer" rows="3" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">{{ old('answer') }}</textarea>
                </x-form-field>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_published" value="1" class="rounded border-aura-300 text-aura-600 focus:ring-aura-400">
                    <span>{{ __('master.faqs.published') }}</span>
                </label>
                <div>
                    <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-medium text-white transition-weightless hover:bg-success-700">{{ __('master.faqs.create_action') }}</button>
                </div>
            </form>

            <div class="mt-6 space-y-3">
                @forelse($faqItems as $faq)
                    <div class="rounded-xl border border-white/80 bg-white/70 p-4">
                        <form method="POST" action="{{ route('admin.faqs.update', $faq) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            @if(auth()->user()?->isSuperadmin())
                                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                            @endif
                            <div class="grid gap-3 md:grid-cols-2">
                                <input type="text" name="category" value="{{ old('category', $faq->category) }}" class="rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                                <input type="text" name="question" value="{{ old('question', $faq->question) }}" class="rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                            </div>
                            <textarea name="answer" rows="3" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">{{ old('answer', $faq->answer) }}</textarea>
                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                <input type="checkbox" name="is_published" value="1" class="rounded border-aura-300 text-aura-600 focus:ring-aura-400" @checked($faq->is_published)>
                                <span>{{ __('master.faqs.published') }}</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <button type="submit" class="rounded-xl bg-success-600 px-3 py-2 text-sm text-white transition-weightless hover:bg-success-700">{{ __('master.faqs.update_action') }}</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            @if(auth()->user()?->isSuperadmin())
                                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                            @endif
                            <button type="submit" class="rounded-xl border border-danger-300/60 bg-danger-50 px-3 py-2 text-xs text-danger-800 transition-weightless hover:bg-danger-100/80">{{ __('master.faqs.delete_action') }}</button>
                        </form>
                    </div>
                @empty
                    <x-empty-state :title="__('master.faqs.empty_title')" :message="__('master.faqs.empty_message')" />
                @endforelse
            </div>

            @if($faqItems instanceof \Illuminate\Contracts\Pagination\Paginator)
                <div class="mt-6">{{ $faqItems->links() }}</div>
            @endif
        @endif
    </x-glass-card>
</x-shell-layout>
