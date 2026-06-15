<x-shell-layout>
    <div class="pt-2 pb-6 font-inter" style="width: 100%; box-sizing: border-box;">
        
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; width: 100%; box-sizing: border-box;">
            <div class="flex items-center" style="display: flex; align-items: center;">
                <a href="{{ route('admin.psy-tests.index') }}" class="mr-4 flex items-center justify-center transition-colors hover:bg-slate-50" style="display: flex; align-items: center; justify-content: center; width: 40px; height: 32px; border: 1px solid #7278F6; border-radius: 4px; color: #7278F6; margin-right: 16px; flex-shrink: 0;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Résultat de {{ $psyTest->candidate_full_name }}</h1>
                    <p class="text-sm text-slate-500 mt-1">Profil : {{ ucfirst($psyTest->profile) }} | Passé le {{ $psyTest->completed_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>
            
            <div class="bg-white border border-slate-200 rounded-xl px-6 py-3 flex items-center shadow-sm" style="display: flex; align-items: center; gap: 20px;">
                <div class="text-right" style="margin-right: 20px;">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">SCORE GLOBAL INDICATIF</div>
                </div>
                <div class="text-3xl font-bold" style="color: #7278F6; flex-shrink: 0;">{{ $psyTest->score }}%</div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm mb-6" style="width: 100%; box-sizing: border-box;">
            <h2 class="text-lg font-bold text-slate-800 mb-6">Profil Dimensionnel</h2>

            <div class="space-y-4">
                @foreach($profileData['dimensions'] as $dimKey => $dimLabel)
                    @php
                        $score = $psyTest->dimension_scores_json['dimension_scores'][$dimKey] ?? 0;
                        $levelText = $score >= 80 ? 'Très élevé' : ($score >= 60 ? 'Élevé' : ($score >= 40 ? 'Moyen' : 'Faible'));
                    @endphp
                    <div class="flex items-center">
                        <div class="w-48 flex-shrink-0 pr-4">
                            <span class="text-sm font-bold text-slate-700">{{ $dimLabel }}</span>
                        </div>
                        <div class="flex-1 px-4">
                            <div style="width: 100%; height: 12px; background-color: #f1f5f9; display: flex;">
                                <div style="height: 100%; width: {{ $score }}%; background-color: #7278F6; transition: width 0.5s ease-out;"></div>
                            </div>
                        </div>
                        <div class="w-32 flex-shrink-0 pl-4 text-right">
                            <span class="text-sm font-semibold text-slate-700">{{ $levelText }} · {{ $score }}%</span>
                        </div>
                    </div>
                @endforeach
            </div>

            @php
                $desPct = $psyTest->dimension_scores_json['desirability_pct'] ?? 0;
            @endphp
            <div class="mt-8 p-4 rounded border {{ $desPct > 75 ? 'bg-rose-50 border-rose-200' : 'bg-green-50 border-green-200' }}">
                <div class="flex items-start">
                    <div class="mr-2 mt-0.5">
                        @if($desPct > 75)
                            <svg class="w-4 h-4 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                        @else
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                        @endif
                    </div>
                    <div>
                        <h4 class="text-sm font-bold {{ $desPct > 75 ? 'text-rose-800' : 'text-green-800' }}">Désirabilité sociale : {{ $desPct }}%</h4>
                        <p class="text-xs mt-1 {{ $desPct > 75 ? 'text-rose-600' : 'text-green-600' }}">
                            @if($desPct > 75)
                                Score très élevé. Le candidat a probablement tenté de falsifier le test en donnant des réponses parfaites plutôt que sincères.
                            @else
                                Sous le seuil d'alerte. Profil de réponses globalement sincère et candide.
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Strengths & Weaknesses (Derived from highest/lowest dimensions) -->
        @php
            $scores = $psyTest->dimension_scores_json['dimension_scores'] ?? [];
            arsort($scores);
            $sortedKeys = array_keys($scores);
            $top2 = array_slice($sortedKeys, 0, 2);
            $bottom2 = array_slice($sortedKeys, -2);
            $topLabels = array_map(fn($k) => $profileData['dimensions'][$k] ?? '', $top2);
            $bottomLabels = array_map(fn($k) => $profileData['dimensions'][$k] ?? '', $bottom2);
        @endphp
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6" style="width: 100%; box-sizing: border-box;">
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <div class="flex items-center mb-2">
                    <span class="text-blue-500 mr-2">📈</span>
                    <h3 class="font-bold text-slate-800 text-sm">Forces dominantes</h3>
                </div>
                <p class="text-xs text-slate-500">Dimensions les plus marquées : {{ implode(' et ', $topLabels) }}.</p>
            </div>
            
            <div class="bg-white border border-rose-100 rounded-2xl p-6 shadow-sm">
                <div class="flex items-center mb-2">
                    <span class="text-rose-500 mr-2">📉</span>
                    <h3 class="font-bold text-rose-800 text-sm">Axes de développement</h3>
                </div>
                <p class="text-xs text-slate-500">Marges de progression sur {{ implode(' et ', $bottomLabels) }}.</p>
            </div>
        </div>

        <!-- Recommended Questions -->
        <div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm mb-6" style="width: 100%; box-sizing: border-box;">
            <h2 class="text-lg font-bold text-slate-800 mb-6">Questions recommandées pour l'entretien</h2>
            
            <div class="space-y-4">
                @if(isset($bottom2[0]))
                    <div class="border border-slate-200 rounded-lg p-4 bg-slate-50">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                            QUESTION LIÉE À L'AXE D'AMÉLIORATION DOMINANT ({{ strtoupper($profileData['dimensions'][$bottom2[0]] ?? '') }}) :
                        </div>
                        <div class="flex">
                            <div class="w-1 bg-rose-400 rounded mr-3"></div>
                            <p class="text-sm font-medium text-slate-700">💬 {{ $profileData['interview_questions'][$bottom2[0]] ?? 'Abordez ce point pour valider ses capacités.' }}</p>
                        </div>
                    </div>
                @endif

                @if(isset($top2[0]))
                    <div class="border border-slate-200 rounded-lg p-4 bg-slate-50">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                            QUESTION LIÉE À LA FORCE DOMINANTE ({{ strtoupper($profileData['dimensions'][$top2[0]] ?? '') }}) :
                        </div>
                        <div class="flex">
                            <div class="w-1 bg-green-500 rounded mr-3"></div>
                            <p class="text-sm font-medium text-slate-700">💬 {{ $profileData['interview_questions'][$top2[0]] ?? 'Demandez-lui des exemples concrets pour illustrer cette force.' }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Raw Answers Traceability -->
        @php
            $answers = $psyTest->answers_json ?? [];
            $questions = $profileData['questions'] ?? [];
            $likertLabels = ['Jamais', 'Rarement', 'Parfois', 'Souvent', 'Toujours'];
        @endphp

        @if(count($answers) > 0 && count($questions) > 0)
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden" style="width: 100%; box-sizing: border-box;" x-data="{ open: false }">
            <!-- Toggle Button -->
            <button @click="open = !open" class="w-full flex items-center justify-between p-6 text-left hover:bg-slate-50 transition-colors" style="display: flex; width: 100%; align-items: center; justify-content: space-between; padding: 24px;">
                <div class="flex items-center space-x-3" style="display: flex; align-items: center; gap: 12px;">
                    <div class="p-2 rounded-lg" style="background: #f0f0ff; flex-shrink: 0; padding: 8px; border-radius: 8px;">
                        <svg class="w-5 h-5" style="color: #7278F6; width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-800" style="margin: 0;">Détail des réponses brutes</h2>
                        <p class="text-xs text-slate-500 mt-0.5" style="margin-top: 2px;">{{ count($answers) }} réponses — toutes les options affichées pour traçabilité</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-sm font-medium" style="color: #7278F6;" x-text="open ? 'Masquer' : 'Afficher les réponses'">Afficher les réponses</span>
                    <svg class="w-5 h-5 transition-transform duration-200" style="color: #7278F6;" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </button>

            <!-- Answers List -->
            <div x-show="open" x-collapse class="border-t border-slate-100">
                <div class="divide-y divide-slate-100">
                    @foreach($questions as $qIdx => $question)
                        @php
                            $chosenIdx = $answers[$qIdx] ?? null;
                            $isLikert = ($question['type'] ?? '') === 'L';
                            $options = $isLikert
                                ? collect($likertLabels)->map(fn($l, $i) => ['label' => $l])->values()->all()
                                : ($question['options'] ?? []);
                        @endphp
                        <div class="p-6">
                            <!-- Question number + text -->
                            <div class="flex items-start mb-4" style="display: flex; align-items: flex-start; margin-bottom: 16px;">
                                <span class="inline-flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background: #7278F6; display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; margin-right: 18px; flex-shrink: 0;">
                                    {{ $qIdx + 1 }}
                                </span>
                                <p class="text-sm font-semibold text-slate-800 leading-relaxed" style="margin-top: 6px;">{{ $question['text'] }}</p>
                            </div>

                            <!-- Options -->
                            <div class="ml-10 space-y-2">
                                @foreach($options as $optIdx => $option)
                                    @php $isChosen = ($chosenIdx !== null && (int)$chosenIdx === $optIdx); @endphp
                                    <div class="flex items-center px-4 py-2.5 rounded-lg border {{ $isChosen ? 'border-indigo-300 bg-indigo-50' : 'border-slate-100 bg-slate-50' }}">
                                        <!-- Radio dot -->
                                        <div class="w-4 h-4 rounded-full border-2 flex-shrink-0 mr-3 flex items-center justify-center {{ $isChosen ? 'border-indigo-500 bg-indigo-500' : 'border-slate-300' }}">
                                            @if($isChosen)
                                                <div class="w-1.5 h-1.5 rounded-full bg-white"></div>
                                            @endif
                                        </div>
                                        <span class="text-sm {{ $isChosen ? 'font-semibold text-indigo-800' : 'text-slate-500' }}">
                                            {{ $option['label'] }}
                                        </span>
                                        @if($isChosen)
                                            <span class="ml-auto text-xs font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">✓ Réponse choisie</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif



    </div>
</x-shell-layout>
