<x-shell-layout title="ATS Dashboard">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-slate-800">ATS Dashboard</h1>
            <p class="text-sm text-slate-500 mt-1">Select a job to manage its AI-ranked candidates or upload new CVs.</p>
        </div>

        <div class="space-y-4">
            @forelse($jobs as $job)
                <div class="flex items-center justify-between rounded-xl border border-slate-200 p-4 hover:border-aura-500 hover:shadow-sm transition">
                    <div>
                        <h3 class="font-semibold text-slate-800">{{ $job->title }}</h3>
                        <p class="text-xs text-slate-500">{{ $job->location ?? 'No location' }} &bull; {{ $job->status }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('ats.upload-cv', $job) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-200">
                            Upload CV
                        </a>
                        <a href="{{ route('ats.candidates', $job) }}" class="rounded-lg bg-aura-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-aura-700">
                            View Candidates
                        </a>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-slate-500">
                    No jobs found. Create a job first.
                </div>
            @endforelse
        </div>
    </div>
</x-shell-layout>
