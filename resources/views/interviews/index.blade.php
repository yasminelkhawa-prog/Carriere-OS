<x-shell-layout :title="__('interviews.title').' | '.config('app.name')">
    <x-glass-card :title="__('interviews.title')" :subtitle="__('interviews.subtitle')">
        @if($requiresCompanySelection)
            <x-empty-state :title="__('kanban.select_company_title')" :message="__('kanban.select_company_message')" />
        @else
            <form method="GET" action="{{ route('interviews.index') }}" class="mb-4 grid gap-3 md:grid-cols-5">
                @if(auth()->user()->isSuperadmin())
                    <x-form-field :label="__('kanban.filters.company')" name="company_id">
                        <select name="company_id" data-placeholder="{{ __('kanban.filters.company_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            <option value="">{{ __('kanban.filters.company_placeholder') }}</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </x-form-field>
                @endif

                <x-form-field :label="__('interviews.filters.job')" name="job_id">
                    <select name="job_id" data-placeholder="{{ __('interviews.filters.job_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                        <option value="">{{ __('interviews.filters.job_placeholder') }}</option>
                        @foreach($jobs as $job)
                            <option value="{{ $job->id }}" @selected((string) $selectedJobId === (string) $job->id)>{{ $job->title }}</option>
                        @endforeach
                    </select>
                </x-form-field>

                <x-form-field :label="__('interviews.filters.interviewer')" name="interviewer_user_id">
                    <select name="interviewer_user_id" data-placeholder="{{ __('interviews.filters.interviewer_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                        <option value="">{{ __('interviews.filters.interviewer_placeholder') }}</option>
                        @foreach($interviewers as $interviewer)
                            <option value="{{ $interviewer->id }}" @selected((string) $selectedInterviewerUserId === (string) $interviewer->id)>{{ $interviewer->profile?->full_name ?? $interviewer->email }}</option>
                        @endforeach
                    </select>
                </x-form-field>

                <x-form-field :label="__('interviews.filters.status')" name="status">
                    <select name="status" data-placeholder="{{ __('interviews.filters.status_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                        <option value="">{{ __('interviews.filters.status_placeholder') }}</option>
                        @foreach(\App\Models\Interview::statuses() as $status)
                            <option value="{{ $status }}" @selected((string) $selectedStatus === (string) $status)>{{ __('interviews.status.'.$status) }}</option>
                        @endforeach
                    </select>
                </x-form-field>

                <div class="flex items-end">
                    <button type="submit" class="w-full rounded-xl bg-success-600 px-3 py-2 text-sm font-semibold text-white">{{ __('kanban.filters.apply') }}</button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-700">{{ __('interviews.list.candidate') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-700">{{ __('interviews.list.job') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-700">{{ __('interviews.list.interviewers') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-700">{{ __('interviews.list.time') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-700">{{ __('interviews.list.status') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-700">{{ __('interviews.list.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($interviews as $interview)
                            <tr>
                                <td class="px-3 py-2 text-slate-800">{{ $interview->application?->candidate?->full_name }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $interview->application?->job?->title }}</td>
                                <td class="px-3 py-2 text-slate-700">
                                    {{ $interview->participants->map(fn($participant) => $participant->user?->profile?->full_name ?? $participant->user?->email)->filter()->implode(', ') }}
                                </td>
                                <td class="px-3 py-2 text-slate-700">{{ $interview->scheduled_start_at?->timezone($interview->timezone)->format('Y-m-d H:i') }}</td>
                                <td class="px-3 py-2"><x-badge>{{ __('interviews.status.'.$interview->status) }}</x-badge></td>
                                <td class="px-3 py-2">
                                    <a href="{{ route('interviews.show', ['interview' => $interview->id, 'company_id' => $selectedCompanyId]) }}" class="rounded-lg border border-aura-200 px-2 py-1 text-xs text-aura-700">
                                        {{ __('interviews.list.open') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6">
                                    <x-empty-state :title="__('interviews.empty_title')" :message="__('interviews.empty_message')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($interviews instanceof \Illuminate\Pagination\AbstractPaginator)
                <div class="mt-4">{{ $interviews->links() }}</div>
            @endif
        @endif
    </x-glass-card>
</x-shell-layout>
