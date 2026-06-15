<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test Psychologique - {{ $psyTest->candidate_first_name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9ff;
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
        }
        /* ── Brand tokens ── */
        :root {
            --numa-violet: #7278F6;
            --numa-violet-light: #9EA5FF;
            --numa-violet-pale: #F0F0FF;
            --numa-azure: #5B6CF9;
            --numa-azure-dark: #4755e0;
        }

        /* ── Header ── */
        .pg-header-wrapper {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: center;
            width: 100%;
        }
        .pg-header {
            width: 100%;
            max-width: 820px;
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ── Timer ── */
        .timer-chip {
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 18px;
            font-weight: 700;
            font-size: 1.15rem;
            color: #1e293b;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.04em;
        }
        .timer-chip.urgent { color: #dc2626; }

        /* ── Progress ── */
        .progress-bar-track {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 99px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--numa-violet-light), var(--numa-violet));
            border-radius: 99px;
            transition: width 0.35s ease;
        }
        .progress-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
        }

        /* ── Cards ── */
        .card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid #e8eaf6;
            box-shadow: 0 2px 16px rgba(114,120,246,0.06);
            padding: 40px 48px;
        }
        @media (max-width: 640px) {
            .card { padding: 24px 20px; }
            .pg-header { padding: 14px 16px; }
        }

        /* ── Option labels ── */
        .option-label {
            display: flex;
            align-items: flex-start;
            padding: 16px 20px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
            margin-bottom: 12px;
            user-select: none;
        }
        .option-label:hover {
            border-color: var(--numa-violet-light);
            background: var(--numa-violet-pale);
        }
        .option-label.selected {
            border-color: var(--numa-violet);
            background: var(--numa-violet-pale);
        }
        .option-label .radio-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            flex-shrink: 0;
            margin-right: 14px;
            margin-top: 2px;
            transition: border-color 0.15s, background 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .option-label.selected .radio-dot {
            border-color: var(--numa-violet);
            background: var(--numa-violet);
        }
        .option-label.selected .radio-dot::after {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: white;
        }

        /* ── Buttons ── */
        .btn-primary {
            background: linear-gradient(135deg, var(--numa-azure), var(--numa-violet));
            color: white;
            font-weight: 700;
            padding: 12px 36px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: opacity 0.15s, transform 0.1s;
            font-family: 'Inter', sans-serif;
        }
        .btn-primary:hover { opacity: 0.92; transform: translateY(-1px); }
        .btn-primary:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

        /* ── Welcome title ── */
        .welcome-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--numa-azure), var(--numa-violet));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        /* ── Question text ── */
        .question-text {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.5;
            margin-bottom: 28px;
        }

        /* ── Alert box ── */
        .alert-info {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 16px 20px;
            text-align: left;
            max-width: 560px;
            margin: 0 auto 32px;
        }
        .alert-info p { margin: 0 0 6px; font-size: 0.92rem; color: #78350f; }
        .alert-info p:last-child { margin: 0; }

        /* ── Likert row ── */
        .likert-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
        }
        @media (max-width: 600px) {
            .likert-grid { grid-template-columns: 1fr 1fr; }
        }
        .likert-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 14px 8px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 500;
            color: #475569;
        }
        .likert-option:hover { border-color: var(--numa-violet-light); background: var(--numa-violet-pale); }
        .likert-option.selected { border-color: var(--numa-violet); background: var(--numa-violet-pale); color: var(--numa-violet); font-weight: 700; }
        .likert-option .likert-dot {
            width: 16px; height: 16px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            margin-bottom: 8px;
            transition: border-color 0.15s, background 0.15s;
        }
        .likert-option.selected .likert-dot {
            border-color: var(--numa-violet);
            background: var(--numa-violet);
        }
    </style>
</head>
<body x-data="psyTestForm({{ count($questions) }}, '{{ route('public.psy-test.submit', $psyTest->token) }}', '{{ csrf_token() }}', {{ $psyTest->expires_at->timestamp * 1000 }})">

    <!-- Header -->
    <div class="pg-header-wrapper">
        <header class="pg-header">
            <div>
                <!-- Numa logo SVG inline -->
                <svg xmlns="http://www.w3.org/2000/svg" width="100" height="32" viewBox="0 0 360 116" fill="none" role="img">
                    <defs>
                        <linearGradient id="numaGradH" x1="16" y1="18" x2="114" y2="108" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#D4D7FF"/>
                            <stop offset="0.5" stop-color="#9EA5FF"/>
                            <stop offset="1" stop-color="#7278F6"/>
                        </linearGradient>
                    </defs>
                    <rect x="18" y="32" width="24" height="24" rx="12" fill="url(#numaGradH)"/>
                    <rect x="43" y="86" width="78" height="24" rx="12" transform="rotate(-58 43 86)" fill="url(#numaGradH)"/>
                    <text x="134" y="77" fill="#0F172A" font-family="Poppins, Arial, sans-serif" font-size="72" font-weight="500">numa</text>
                </svg>
            </div>

            <!-- Timer -->
            <div class="timer-chip" :class="{ 'urgent': timeIsUrgent }" x-text="timeLeftFormatted">00:00</div>
        </header>
    </div>

    <main style="flex: 1; display: flex; flex-direction: column; align-items: center; padding: 40px 16px; width: 100%; max-width: 820px; margin: 0 auto;">

        <!-- Welcome Screen -->
        <div x-show="!started" style="width: 100%; text-align: center;">
            <div class="card" style="max-width: 640px; margin: 0 auto;">
                <div class="welcome-title">Test Psychologique</div>
                <p style="color: #64748b; font-size: 1.05rem; margin-bottom: 6px;">Bonjour <strong>{{ $psyTest->candidate_first_name }}</strong>.</p>
                <p style="color: #64748b; margin-bottom: 28px; line-height: 1.6;">Ce test contient une série de questions pour mieux comprendre votre profil.<br>Il n'y a pas de mauvaises réponses, soyez spontané(e).</p>

                <div class="alert-info">
                    <p>⏳ Vous avez jusqu'au <strong>{{ $psyTest->expires_at->format('d/m à H:i') }}</strong> pour terminer ce test.</p>
                    <p>⚠️ Attention : Une fois validé, vous ne pourrez plus modifier vos réponses.</p>
                </div>

                <button @click="startTest()" class="btn-primary">
                    Commencer le test
                </button>
            </div>
        </div>

        <!-- Questions -->
        <div x-show="started" style="width: 100%;" x-cloak>

            <!-- Progress -->
            <div style="margin-bottom: 20px;">
                <div class="progress-bar-track">
                    <div class="progress-bar-fill" :style="`width: ${((currentStep + 1) / totalSteps) * 100}%`"></div>
                </div>
                <div class="progress-label">Question <span x-text="currentStep + 1"></span> / <span x-text="totalSteps"></span></div>
            </div>

            <div class="card">
                @foreach($questions as $index => $question)
                    <div x-show="currentStep === {{ $index }}" x-cloak>

                        <div class="question-text">{{ $question['text'] }}</div>

                        @if($question['type'] === 'L')
                            @php $likertOptions = ['Jamais', 'Rarement', 'Parfois', 'Souvent', 'Toujours']; @endphp
                            <div class="likert-grid">
                                @foreach($likertOptions as $optIdx => $optLabel)
                                    <div class="likert-option" :class="answers[{{ $index }}] === {{ $optIdx }} ? 'selected' : ''" @click="selectAnswer({{ $index }}, {{ $optIdx }})">
                                        <div class="likert-dot"></div>
                                        {{ $optLabel }}
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div>
                                @foreach($question['options'] as $optIdx => $option)
                                    <div class="option-label" :class="answers[{{ $index }}] === {{ $optIdx }} ? 'selected' : ''" @click="selectAnswer({{ $index }}, {{ $optIdx }})">
                                        <div class="radio-dot"></div>
                                        <span style="font-size: 1rem; line-height: 1.55; color: #1e293b;">{{ $option['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Navigation -->
                        <div style="display: flex; justify-content: flex-end; margin-top: 32px;">
                            <button class="btn-primary" @click="nextStepOrSubmit()" :disabled="answers[{{ $index }}] === null || isSubmitting">
                                <span x-show="!isSubmitting" x-text="currentStep === totalSteps - 1 ? 'Terminer' : 'Suivant'">Suivant</span>
                                <span x-show="isSubmitting">...</span>
                            </button>
                        </div>

                    </div>
                @endforeach

                <div x-show="errorMsg" style="margin-top: 16px; padding: 12px 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #dc2626; font-size: 0.875rem;" x-text="errorMsg" x-cloak></div>
            </div>
        </div>
    </main>

    <style>[x-cloak] { display: none !important; }</style>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('psyTestForm', (total, submitUrl, token, expireTimestamp) => ({
                started: false,
                currentStep: 0,
                totalSteps: total,
                answers: Array(total).fill(null),
                isSubmitting: false,
                errorMsg: '',
                timeLeftFormatted: '00:00',
                timeIsUrgent: false,
                timerInterval: null,

                init() {
                    this.updateTimer();
                    this.timerInterval = setInterval(() => this.updateTimer(), 1000);
                },

                updateTimer() {
                    const distance = expireTimestamp - Date.now();
                    if (distance < 0) {
                        clearInterval(this.timerInterval);
                        this.timeLeftFormatted = 'Expiré';
                        this.timeIsUrgent = true;
                        return;
                    }
                    const h = Math.floor(distance / 3600000);
                    const m = Math.floor((distance % 3600000) / 60000);
                    const s = Math.floor((distance % 60000) / 1000);
                    this.timeIsUrgent = (distance < 300000); // last 5 min
                    this.timeLeftFormatted = h > 0
                        ? `${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`
                        : `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
                },

                startTest() { this.started = true; },

                selectAnswer(index, value) { this.answers[index] = value; },

                nextStepOrSubmit() {
                    if (this.currentStep < this.totalSteps - 1) {
                        this.currentStep++;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    } else {
                        this.submitTest();
                    }
                },

                async submitTest() {
                    if (this.answers.includes(null)) {
                        this.errorMsg = 'Veuillez répondre à toutes les questions avant de soumettre.';
                        return;
                    }
                    this.isSubmitting = true;
                    this.errorMsg = '';
                    try {
                        const response = await fetch(submitUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                            body: JSON.stringify({ answers: this.answers })
                        });
                        const data = await response.json();
                        if (response.ok) {
                            window.location.href = data.redirect;
                        } else {
                            this.errorMsg = data.message || 'Une erreur est survenue.';
                            this.isSubmitting = false;
                        }
                    } catch {
                        this.errorMsg = 'Erreur de connexion. Veuillez réessayer.';
                        this.isSubmitting = false;
                    }
                }
            }));
        });
    </script>
</body>
</html>
