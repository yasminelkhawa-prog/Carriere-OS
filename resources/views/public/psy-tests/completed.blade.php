<!DOCTYPE html>
<html lang="fr" class="bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test Terminé - {{ $psyTest->candidate_first_name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-inter antialiased text-slate-600 min-h-screen flex flex-col relative overflow-hidden">
    <!-- Background styling -->
    <div class="absolute inset-0 bg-[url('https://carriereos.com/images/bg-pattern.svg')] opacity-5 pointer-events-none"></div>
    <div class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-emerald-900 to-slate-50 -z-10"></div>

    <header class="w-full py-6 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto flex justify-start items-center z-10">
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
    </header>

    <main class="flex-grow flex items-center justify-center px-4 z-10" style="min-height: calc(100vh - 100px);">
        <div class="w-full max-w-3xl bg-white rounded-3xl shadow-xl border border-slate-200/60 p-16 sm:p-20 text-center">
            
            <div class="bg-emerald-100 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-10" style="width: 100px; height: 100px;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 52px; height: 52px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            
            <h1 class="font-bold text-slate-800 mb-5" style="font-size: 2.5rem; line-height: 1.2;">Test complété avec succès !</h1>
            
            <p class="text-slate-500 mb-12 leading-relaxed" style="font-size: 1.25rem;">
                Merci {{ $psyTest->candidate_first_name }}. Vos réponses ont bien été enregistrées. Le recruteur a été notifié et reviendra vers vous prochainement.
            </p>

            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100">
                <p class="text-slate-500 font-medium" style="font-size: 1.05rem;">Vous pouvez maintenant fermer cette page.</p>
            </div>

        </div>
    </main>
</body>
</html>
