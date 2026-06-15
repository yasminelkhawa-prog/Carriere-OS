<x-shell-layout :title="'Ranked Candidates for ' . $job->title">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Ranked Candidates</h1>
                <p class="text-sm text-slate-500 mt-1">Candidates ranked by AI for: <strong>{{ $job->title }}</strong></p>
            </div>
            <a href="{{ route('ats.dashboard') }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-200">Back to Dashboard</a>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-xl bg-green-50 p-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500">
                        <th class="py-3 font-medium">Candidate</th>
                        <th class="py-3 font-medium">Score</th>
                        <th class="py-3 font-medium">Experience</th>
                        <th class="py-3 font-medium">Education</th>
                        <th class="py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($applications as $app)
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 font-medium text-slate-800">{{ $app->candidate->full_name ?? 'Unknown' }}</td>
                            <td class="py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                    @if($app->score >= 80) bg-green-100 text-green-800
                                    @elseif($app->score >= 60) bg-yellow-100 text-yellow-800
                                    @else bg-red-100 text-red-800 @endif
                                ">
                                    {{ $app->score }} / 100
                                </span>
                            </td>
                            <td class="py-3 text-slate-600">{{ $app->ai_result_json['experience_years'] ?? 'N/A' }} years</td>
                            <td class="py-3 text-slate-600">{{ $app->ai_result_json['education'] ?? 'N/A' }}</td>
                            <td class="py-3 text-right">
                                <a href="{{ route('ats.show-candidate', $app) }}" class="text-aura-600 font-medium hover:text-aura-700">View Details</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-500">No candidates uploaded via ATS yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-shell-layout>
