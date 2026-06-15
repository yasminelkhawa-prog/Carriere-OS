<x-shell-layout :title="'Upload CV to ' . $job->title">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-slate-800">Upload CV</h1>
            <p class="text-sm text-slate-500 mt-1">Upload a PDF or DOCX file. The AI will parse it and score it against: <strong>{{ $job->title }}</strong></p>
        </div>

        @if($errors->any())
            <div class="mb-6 rounded-xl bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('ats.store-cv', $job) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <div>
                <label for="cv_file" class="block text-sm font-medium text-slate-700">CV Document (PDF or DOCX)</label>
                <input type="file" name="cv_file" id="cv_file" accept=".pdf,.docx,.doc" class="mt-2 block w-full rounded-xl border border-slate-200 text-sm text-slate-900 file:mr-4 file:rounded-xl file:border-0 file:bg-aura-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-aura-700 hover:file:bg-aura-100" required>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('ats.dashboard') }}" class="rounded-xl px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200">Cancel</a>
                <button type="submit" class="rounded-xl bg-aura-600 px-4 py-2 text-sm font-medium text-white hover:bg-aura-700">Upload & Analyze</button>
            </div>
        </form>
    </div>
</x-shell-layout>
