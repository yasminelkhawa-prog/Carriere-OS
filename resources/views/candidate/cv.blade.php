<x-shell-layout :title="__('candidate_portal.cv.title').' | '.config('app.name')">
    <div class="space-y-6 pb-16">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif


        <x-glass-card
            id="candidate-cv-upload"
            class="relative overflow-hidden"
            :title="__('candidate_portal.cv.upload.title')"
            :subtitle="__('candidate_portal.cv.upload.subtitle')">
            <form method="POST" action="{{ route('candidate.cv.upload', ['company' => $company->slug]) }}" enctype="multipart/form-data" class="relative z-10 w-full max-w-sm space-y-3">
                @csrf

                <x-form-field :label="__('candidate_portal.cv.upload.label')" name="file" required class="min-w-[240px]">
                    <input type="file" name="file" accept=".pdf,.doc,.docx" required class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-xl file:border-0 file:bg-aura-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-aura-700 hover:file:bg-aura-200">
                </x-form-field>

                <button type="submit" class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                    {{ __('candidate_portal.cv.upload.submit') }}
                </button>
            </form>

            <img src="{{ asset('images/cv-upload-illustration.webp') }}" alt="" class="pointer-events-none absolute bottom-0 right-4 hidden h-36 w-auto object-contain sm:block" aria-hidden="true">
        </x-glass-card>

        <x-glass-card
            id="candidate-cv-data"
            :title="__('candidate_portal.cv.data.title')"
            :subtitle="__('candidate_portal.cv.data.subtitle')">

            @unless($hasCvData)
                <p class="mb-5 rounded-2xl border border-dashed border-aura-300/50 bg-white/55 p-4 text-sm text-slate-700">
                    {{ __('candidate_portal.cv.data.empty') }}
                </p>
            @endunless

            <form
                method="POST"
                action="{{ route('candidate.cv.data.update', ['company' => $company->slug]) }}"
                x-data="{
                    education: @js(count($cvEducationEntries) ? $cvEducationEntries : [['institution_name' => '', 'degree_name' => '', 'field_of_study' => '', 'start_date' => '', 'end_date' => '']]),
                    experience: @js(count($cvExperienceEntries) ? $cvExperienceEntries : [['job_title' => '', 'company_name' => '', 'start_date' => '', 'end_date' => '', 'description' => '']]),
                    certifications: @js(count($cvCertificationEntries) ? $cvCertificationEntries : [['name' => '', 'issuer' => '', 'date' => '']]),
                }"
                class="space-y-8"
            >
                @csrf

                <div class="grid gap-4 md:grid-cols-2">
                    <x-form-field :label="__('candidate_portal.cv.data.summary')" name="profile_summary" class="md:col-span-2">
                        <textarea name="profile_summary" rows="3" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">{{ old('profile_summary', $cvProfileSummary) }}</textarea>
                    </x-form-field>

                    <x-form-field :label="__('candidate_portal.cv.data.years_experience')" name="total_years_experience">
                        <input type="number" name="total_years_experience" min="0" max="60" step="0.5" value="{{ old('total_years_experience', $cvTotalYearsExperience) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    </x-form-field>

                    <x-form-field :label="__('candidate_portal.cv.data.languages')" name="languages" :help="__('candidate_portal.cv.data.list_help')">
                        <input type="text" name="languages" value="{{ old('languages', $cvLanguages) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    </x-form-field>

                    <x-form-field :label="__('candidate_portal.cv.data.hard_skills')" name="hard_skills" :help="__('candidate_portal.cv.data.list_help')">
                        <textarea name="hard_skills" rows="2" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">{{ old('hard_skills', $cvHardSkills) }}</textarea>
                    </x-form-field>

                    <x-form-field :label="__('candidate_portal.cv.data.soft_skills')" name="soft_skills" :help="__('candidate_portal.cv.data.list_help')">
                        <textarea name="soft_skills" rows="2" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">{{ old('soft_skills', $cvSoftSkills) }}</textarea>
                    </x-form-field>

                    <x-form-field :label="__('candidate_portal.cv.data.tools_frameworks')" name="tools_frameworks" :help="__('candidate_portal.cv.data.list_help')" class="md:col-span-2">
                        <textarea name="tools_frameworks" rows="2" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">{{ old('tools_frameworks', $cvToolsFrameworks) }}</textarea>
                    </x-form-field>
                </div>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700">{{ __('candidate_portal.cv.data.education.title') }}</h3>
                    <div class="mt-3 space-y-3">
                        <template x-for="(item, index) in education" :key="index">
                            <div class="grid gap-3 rounded-2xl border border-white/80 bg-white/70 p-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-slate-800">{{ __('candidate_portal.cv.data.education.institution_name') }}</label>
                                    <input type="text" x-model="item.institution_name" :name="`education[${index}][institution_name]`" class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-800">{{ __('candidate_portal.cv.data.education.degree_name') }}</label>
                                    <input type="text" x-model="item.degree_name" :name="`education[${index}][degree_name]`" class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-800">{{ __('candidate_portal.cv.data.education.field_of_study') }}</label>
                                    <input type="text" x-model="item.field_of_study" :name="`education[${index}][field_of_study]`" class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-800">{{ __('candidate_portal.cv.data.education.start_date') }}</label>
                                        <input type="text" x-model="item.start_date" :name="`education[${index}][start_date]`" placeholder="{{ __('candidate_portal.cv.data.date_placeholder') }}" class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-800">{{ __('candidate_portal.cv.data.education.end_date') }}</label>
                                        <input type="text" x-model="item.end_date" :name="`education[${index}][end_date]`" placeholder="{{ __('candidate_portal.cv.data.date_placeholder') }}" class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                    </div>
                                </div>
                                <div class="md:col-span-2 flex justify-end">
                                    <button type="button" @click="education.splice(index, 1)" x-show="education.length > 1" class="text-xs font-semibold text-danger-600 hover:underline">
                                        {{ __('candidate_portal.cv.data.education.remove') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="education.push({institution_name: '', degree_name: '', field_of_study: '', start_date: '', end_date: ''})" class="mt-3 rounded-xl border border-aura-300 px-3 py-1.5 text-xs font-semibold text-aura-700 transition-weightless hover:bg-aura-50">
                        {{ __('candidate_portal.cv.data.education.add') }}
                    </button>
                </div>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700">{{ __('candidate_portal.cv.data.experience.title') }}</h3>
                    <div class="mt-3 space-y-3">
                        <template x-for="(item, index) in experience" :key="index">
                            <div class="grid gap-3 rounded-2xl border border-white/80 bg-white/70 p-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-slate-800">{{ __('candidate_portal.cv.data.experience.job_title') }}</label>
                                    <input type="text" x-model="item.job_title" :name="`experience[${index}][job_title]`" class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-800">{{ __('candidate_portal.cv.data.experience.company_name') }}</label>
                                    <input type="text" x-model="item.company_name" :name="`experience[${index}][company_name]`" class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-800">{{ __('candidate_portal.cv.data.experience.start_date') }}</label>
                                        <input type="text" x-model="item.start_date" :name="`experience[${index}][start_date]`" placeholder="{{ __('candidate_portal.cv.data.date_placeholder') }}" class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-800">{{ __('candidate_portal.cv.data.experience.end_date') }}</label>
                                        <input type="text" x-model="item.end_date" :name="`experience[${index}][end_date]`" placeholder="{{ __('candidate_portal.cv.data.date_placeholder') }}" class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                    </div>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-slate-800">{{ __('candidate_portal.cv.data.experience.description') }}</label>
                                    <textarea x-model="item.description" :name="`experience[${index}][description]`" rows="2" class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300"></textarea>
                                </div>
                                <div class="md:col-span-2 flex justify-end">
                                    <button type="button" @click="experience.splice(index, 1)" x-show="experience.length > 1" class="text-xs font-semibold text-danger-600 hover:underline">
                                        {{ __('candidate_portal.cv.data.experience.remove') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="experience.push({job_title: '', company_name: '', start_date: '', end_date: '', description: ''})" class="mt-3 rounded-xl border border-aura-300 px-3 py-1.5 text-xs font-semibold text-aura-700 transition-weightless hover:bg-aura-50">
                        {{ __('candidate_portal.cv.data.experience.add') }}
                    </button>
                </div>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700">{{ __('candidate_portal.cv.data.certifications.title') }}</h3>
                    <div class="mt-3 space-y-3">
                        <template x-for="(item, index) in certifications" :key="index">
                            <div class="grid gap-3 rounded-2xl border border-white/80 bg-white/70 p-4 md:grid-cols-3">
                                <div>
                                    <label class="block text-sm font-medium text-slate-800">{{ __('candidate_portal.cv.data.certifications.name') }}</label>
                                    <input type="text" x-model="item.name" :name="`certifications[${index}][name]`" class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-800">{{ __('candidate_portal.cv.data.certifications.issuer') }}</label>
                                    <input type="text" x-model="item.issuer" :name="`certifications[${index}][issuer]`" class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                </div>
                                <div class="flex items-end gap-3">
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-slate-800">{{ __('candidate_portal.cv.data.certifications.date') }}</label>
                                        <input type="text" x-model="item.date" :name="`certifications[${index}][date]`" placeholder="{{ __('candidate_portal.cv.data.date_placeholder') }}" class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                    </div>
                                    <button type="button" @click="certifications.splice(index, 1)" x-show="certifications.length > 1" class="pb-2.5 text-xs font-semibold text-danger-600 hover:underline">
                                        {{ __('candidate_portal.cv.data.certifications.remove') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="certifications.push({name: '', issuer: '', date: ''})" class="mt-3 rounded-xl border border-aura-300 px-3 py-1.5 text-xs font-semibold text-aura-700 transition-weightless hover:bg-aura-50">
                        {{ __('candidate_portal.cv.data.certifications.add') }}
                    </button>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-aura-700 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-aura-800">
                        {{ __('candidate_portal.cv.data.submit') }}
                    </button>
                </div>
            </form>
        </x-glass-card>

        <x-glass-card
            id="candidate-cv-history"
            :title="__('candidate_portal.cv.history.title')"
            :subtitle="__('candidate_portal.cv.history.subtitle')">

            @if ($cvDocuments->isEmpty())
                <x-empty-state :title="__('candidate_portal.cv.history.title')" :message="__('candidate_portal.cv.history.empty')" />
            @else
                <div class="space-y-3">
                    @foreach ($cvDocuments as $entry)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/80 bg-white/70 p-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $entry['document']->original_filename }}</p>
                                <p class="mt-1 text-xs text-slate-600">
                                    {{ __('candidate_portal.cv.history.uploaded_on') }} {{ $entry['document']->created_at?->translatedFormat('d/m/Y H:i') }}
                                    @if ($entry['document']->file_size_bytes)
                                        · {{ number_format($entry['document']->file_size_bytes / 1024, 0) }} Ko
                                    @endif
                                </p>
                            </div>
                            <a href="{{ $entry['url'] }}" target="_blank" rel="noopener" class="rounded-xl border border-aura-300 px-3 py-1.5 text-xs font-semibold text-aura-700 transition-weightless hover:bg-aura-50">
                                {{ __('candidate_portal.cv.history.view') }}
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-glass-card>
    </div>
</x-shell-layout>
