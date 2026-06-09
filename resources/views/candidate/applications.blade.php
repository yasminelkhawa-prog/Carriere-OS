<x-shell-layout :title="__('ui.nav.candidate_applications').' | '.config('app.name')">
    <div class="space-y-6">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif
        @if($errors->any())
            <x-toast-alert type="warning">{{ $errors->first() }}</x-toast-alert>
        @endif

        <x-glass-card :title="__('candidate_portal.applications.title')" :subtitle="__('candidate_portal.applications.subtitle')">
            @include('candidate.partials.applications-list', [
                'company' => $company,
                'applications' => $applications,
                'nextSteps' => $nextSteps,
                'hiredFlowApplications' => $hiredFlowApplications,
                'reverseFeedbackEligibility' => $reverseFeedbackEligibility,
                'statusTrackers' => $statusTrackers,
                'xaiInsights' => $xaiInsights,
            ])
        </x-glass-card>

        @if(($strategyLabBriefs ?? collect())->isNotEmpty())
            @include('candidate.partials.strategy-lab-list', ['company' => $company, 'strategyLabBriefs' => $strategyLabBriefs])
        @endif
    </div>
</x-shell-layout>
