<x-shell-layout :title="__('referrals.index.title').' | '.config('app.name')">
    <section class="space-y-4">
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
            <x-glass-card>
                <x-empty-state :title="__('referrals.company_required.title')" :message="__('referrals.company_required.message')" />
            </x-glass-card>
        @else
            <x-glass-card class="p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="panel-title text-3xl font-semibold tracking-tight text-slate-900">{{ __('referrals.index.heading') }}</h1>
                        <p class="mt-1 text-sm text-slate-600">{{ __('referrals.index.subheading') }}</p>
                    </div>
                    <a href="{{ route('referrals.create', array_filter(['company_id' => request('company_id')])) }}" class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                        {{ __('referrals.actions.new_referral') }}
                    </a>
                </div>
            </x-glass-card>

            <x-glass-card class="p-4">
                <form method="GET" action="{{ route('referrals.index') }}" class="grid gap-3 md:grid-cols-4">
                    @if(auth()->user()?->isSuperadmin())
                        <x-form-field :label="__('jobs.company')" name="company_id">
                            <select name="company_id" data-placeholder="{{ __('jobs.company_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                <option value="">{{ __('jobs.company_placeholder') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </x-form-field>
                    @endif

                    <x-form-field :label="__('referrals.filters.status')" name="status">
                        <select name="status" data-placeholder="{{ __('referrals.filters.status_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            <option value="">{{ __('referrals.filters.status_placeholder') }}</option>
                            @foreach($statusOptions as $status)
                                <option value="{{ $status }}" @selected((string) ($filters['status'] ?? '') === (string) $status)>{{ __('referrals.status.'.$status) }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    @if($canViewAll)
                        <x-form-field :label="__('referrals.filters.referrer')" name="referrer_user_id">
                            <select name="referrer_user_id" data-placeholder="{{ __('referrals.filters.referrer_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                <option value="">{{ __('referrals.filters.referrer_placeholder') }}</option>
                                @foreach($referrerOptions as $referrer)
                                    <option value="{{ $referrer['id'] }}" @selected((string) ($filters['referrer_user_id'] ?? '') === (string) $referrer['id'])>{{ $referrer['name'] }}</option>
                                @endforeach
                            </select>
                        </x-form-field>
                    @endif

                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                            {{ __('referrals.filters.apply') }}
                        </button>
                        <a href="{{ route('referrals.index', array_filter(['company_id' => request('company_id')])) }}" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-weightless hover:bg-slate-50">
                            {{ __('referrals.filters.reset') }}
                        </a>
                    </div>
                </form>
            </x-glass-card>

            <x-glass-card class="p-0">
                @if($referrals->isEmpty())
                    <div class="px-5 py-5">
                        <x-empty-state :title="__('referrals.index.empty_title')" :message="__('referrals.index.empty_message')" />
                    </div>
                @else
                    <x-table>
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('referrals.columns.candidate') }}</th>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('referrals.columns.referrer') }}</th>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('referrals.columns.status') }}</th>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('referrals.columns.created') }}</th>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('referrals.columns.application') }}</th>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('referrals.columns.action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($referrals as $referral)
                                @php
                                    $isTerminal = in_array((string) $referral->status, ['hired', 'rejected'], true);
                                    $linkedApplication = $referral->linkedApplication;
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        <p class="font-semibold text-slate-900">{{ $referral->candidate_name ?: __('referrals.not_available') }}</p>
                                        <p class="text-xs text-slate-600">{{ $referral->candidate_email }}</p>
                                        @if($referral->candidate_linkedin_url)
                                            <a href="{{ $referral->candidate_linkedin_url }}" target="_blank" rel="noopener" class="text-xs text-aura-700 hover:underline">
                                                {{ __('referrals.columns.linkedin') }}
                                            </a>
                                        @endif
                                        @if($referral->resume_file_url)
                                            <p class="text-xs text-slate-500">{{ __('referrals.columns.resume_attached') }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        {{ $referral->referrer?->profile?->full_name ?? $referral->referrer?->email ?? __('referrals.not_available') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                            @if($referral->status === 'hired') bg-success-100 text-success-700
                                            @elseif($referral->status === 'rejected') bg-danger-100 text-danger-700
                                            @elseif($referral->status === 'converted') bg-success-100 text-success-700
                                            @else bg-slate-100 text-slate-700
                                            @endif">
                                            {{ __('referrals.status.'.$referral->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ optional($referral->created_at)->diffForHumans() }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        @if($linkedApplication)
                                            <a href="{{ route('candidates.index', array_filter(array_merge(['application_id' => $linkedApplication->id], auth()->user()?->isSuperadmin() ? ['company_id' => request('company_id')] : []))) }}" class="text-aura-700 hover:underline">
                                                {{ $linkedApplication->job?->title ?: __('referrals.actions.open_application') }}
                                            </a>
                                        @else
                                            {{ __('referrals.not_available') }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        @if($canConvert && ! $isTerminal && ! $linkedApplication)
                                            <form method="POST" action="{{ route('referrals.convert', array_filter(['referral' => $referral->id, 'company_id' => request('company_id')])) }}" class="grid grid-cols-[minmax(11rem,1fr)_auto] items-center gap-2">
                                                @csrf
                                                @if(auth()->user()?->isSuperadmin() && request('company_id'))
                                                    <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                                                @endif
                                                <input type="hidden" name="referral_id" value="{{ $referral->id }}">
                                                <select name="job_id" required data-placeholder="{{ __('referrals.fields.job_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-2 py-1.5 text-xs">
                                                    <option value="">{{ __('referrals.fields.job_placeholder') }}</option>
                                                    @foreach($jobs as $job)
                                                        <option value="{{ $job->id }}" @selected((string) old('referral_id') === (string) $referral->id && (string) old('job_id') === (string) $job->id)>{{ $job->title }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="shrink-0 rounded-lg border border-success-300/60 bg-success-50 px-2 py-1 text-xs font-semibold text-success-800 hover:bg-success-100/80">
                                                    {{ __('referrals.actions.convert') }}
                                                </button>
                                                @if((string) old('referral_id') === (string) $referral->id && ($errors->has('job_id') || $errors->has('referral')))
                                                    <p class="col-span-2 text-xs text-danger-700">{{ $errors->first('job_id') ?: $errors->first('referral') }}</p>
                                                @endif
                                            </form>
                                        @elseif($linkedApplication)
                                            <span class="text-xs text-slate-600">{{ __('referrals.actions.converted') }}</span>
                                        @else
                                            <span class="text-xs text-slate-500">{{ __('referrals.not_available') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-glass-card>

            @if($referrals instanceof \Illuminate\Contracts\Pagination\Paginator)
                <div>{{ $referrals->links() }}</div>
            @endif
        @endif
    </section>
</x-shell-layout>
