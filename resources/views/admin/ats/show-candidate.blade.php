<x-shell-layout :title="'Candidate Details: ' . ($application->candidate->full_name ?? 'Unknown')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">{{ $application->candidate->full_name ?? 'Unknown' }}</h1>
                <p class="text-slate-500">Applied for <strong>{{ $application->job->title }}</strong></p>
            </div>
            <a href="{{ route('ats.candidates', $application->job) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-200">Back to List</a>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <!-- Left Column: Metrics -->
            <div class="space-y-6">
                <!-- AI Score Card -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wider mb-4">AI Match Score</h3>
                    <div class="flex items-end gap-3">
                        <span class="text-5xl font-black 
                            @if($application->score >= 80) text-green-600
                            @elseif($application->score >= 60) text-yellow-600
                            @else text-red-600 @endif">
                            {{ $application->score }}
                        </span>
                        <span class="text-xl text-slate-400 font-medium pb-1">/ 100</span>
                    </div>
                </div>

                <!-- Basic Info -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wider mb-4">Extracted Info</h3>
                    <ul class="space-y-4">
                        <li>
                            <span class="block text-xs text-slate-400">Experience</span>
                            <span class="font-medium text-slate-800">{{ $application->ai_result_json['experience_years'] ?? 'N/A' }} years</span>
                        </li>
                        <li>
                            <span class="block text-xs text-slate-400">Education</span>
                            <span class="font-medium text-slate-800">{{ $application->ai_result_json['education'] ?? 'N/A' }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Skills -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wider mb-4">Skills</h3>
                    <div class="flex flex-wrap gap-2">
                        @forelse($application->ai_result_json['skills'] ?? [] as $skill)
                            <span class="inline-flex items-center rounded-full bg-aura-50 px-2.5 py-0.5 text-xs font-semibold text-aura-700 border border-aura-200">
                                {{ $skill }}
                            </span>
                        @empty
                            <span class="text-sm text-slate-500">No skills identified.</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Right Column: AI Reasoning and Original CV -->
            <div class="md:col-span-2 space-y-6">
                <!-- Reasoning -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wider mb-4">AI Reasoning</h3>
                    <ul class="space-y-3 list-disc pl-5 text-slate-700">
                        @forelse($application->ai_result_json['reasoning'] ?? [] as $reason)
                            <li>{{ $reason }}</li>
                        @empty
                            <li class="text-slate-500 list-none -ml-5">No reasoning provided by AI.</li>
                        @endforelse
                    </ul>
                </div>

                <!-- Parsed Text -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wider">Parsed CV Text</h3>
                        @if($application->cv)
                            <a href="#" class="text-sm text-aura-600 hover:text-aura-700 font-medium">Download Original</a>
                        @endif
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4 text-sm text-slate-700 whitespace-pre-wrap font-mono overflow-y-auto max-h-96">
                        {{ $application->cv->extracted_text ?? 'No parsed text available.' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-shell-layout>
