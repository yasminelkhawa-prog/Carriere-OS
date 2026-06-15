<x-shell-layout title="Dashboard | {{ config('app.name') }}">
@if($requiresCompanySelection)
    <div class="p-6">
        <x-empty-state title="Dashboard" message="Veuillez sélectionner une entreprise pour accéder à votre tableau de bord." />
    </div>
@else

<style>
:root {
    --c-blue: #3B82F6;
    --c-green: #10B981;
    --c-amber: #F59E0B;
    --c-pink: #EC4899;
    --c-purple: #8B5CF6;
    --c-cyan: #06B6D4;
    --c-slate: #64748B;
}

.db-wrap {
    background: #f1f5f9;
    padding: 1.5rem;
    min-height: 100vh;
}

.db-title {
    font-size: 1.35rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 1.25rem;
    letter-spacing: -0.02em;
}

.db-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.db-card {
    background: #fff;
    border-radius: 1.25rem;
    padding: 1.25rem;
    box-shadow: 0 2px 12px -2px rgba(0,0,0,0.06);
}

.db-card-title {
    font-size: 0.72rem;
    font-weight: 700;
    color: #1e293b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.2rem;
}

.db-card-sub {
    font-size: 0.65rem;
    color: #94a3b8;
    margin-bottom: 1rem;
}

/* Span helpers */
.span-1 { grid-column: span 1; }
.span-2 { grid-column: span 2; }
.span-3 { grid-column: span 3; }

/* === GENDER WIDGET === */
.gender-row {
    display: flex;
    align-items: center;
    justify-content: space-around;
    gap: 1rem;
}

.gender-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
}

.gender-figure {
    position: relative;
    width: 80px;
    height: 110px;
    margin-bottom: 0.5rem;
}

.gender-icon-bg { position: absolute; inset: 0; }
.gender-icon-fill {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    overflow: hidden;
    transition: height 1s ease;
}

.gender-pct {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 900;
}

.gender-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: #334155;
    margin-bottom: 0.25rem;
}

.gender-badge {
    font-size: 0.6rem;
    background: #f1f5f9;
    color: #64748b;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    font-weight: 600;
}

/* === DONUT === */
.donut-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
}

.donut-svg {
    width: 120px;
    height: 120px;
}

.donut-legend {
    width: 100%;
}

.legend-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.65rem;
    padding: 0.2rem 0;
}

.legend-dot {
    width: 8px; height: 8px;
    border-radius: 2px;
    display: inline-block;
    margin-right: 0.35rem;
    flex-shrink: 0;
}

.legend-left {
    display: flex;
    align-items: center;
    color: #475569;
    font-weight: 600;
}

.legend-right {
    font-weight: 800;
    color: #1e293b;
    display: flex;
    gap: 0.3rem;
    align-items: center;
}

.legend-badge {
    font-size: 0.58rem;
    padding: 0.1rem 0.35rem;
    border-radius: 9999px;
    font-weight: 700;
}

/* === SITES === */
.site-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.4rem 0;
    font-size: 0.68rem;
    border-bottom: 1px solid #f1f5f9;
}

.site-row:last-child { border-bottom: none; }

.site-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-right: 0.4rem;
}

.site-name {
    flex: 1;
    color: #475569;
    font-weight: 600;
}

.site-count {
    font-weight: 800;
    color: #1e293b;
    font-size: 0.8rem;
}

/* === COMPANY CARDS === */
.company-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.company-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.company-logo {
    width: 42px; height: 42px;
    border-radius: 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 900;
    margin-bottom: 0.35rem;
}

.company-name {
    font-size: 0.55rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.15rem;
}

.company-count {
    font-size: 1.5rem;
    font-weight: 900;
    line-height: 1;
}

.company-pct {
    font-size: 0.6rem;
    color: #94a3b8;
}

/* === SOURCING TOOLS === */
.tools-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}

.tool-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
}

.tool-icon {
    width: 44px; height: 44px;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 900;
    color: white;
}

.tool-count {
    font-size: 1.4rem;
    font-weight: 900;
    line-height: 1;
}

.tool-name {
    font-size: 0.58rem;
    color: #94a3b8;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* === TAUX CLOTURE BAR CHART === */
.bar-chart {
    display: flex;
    align-items: flex-end;
    gap: 0.4rem;
    height: 120px;
    padding-top: 1rem;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 0.5rem;
}

.bar-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
    gap: 0.25rem;
}

.bar-fill {
    width: 100%;
    border-radius: 4px 4px 0 0;
    min-height: 2px;
    transition: height 0.8s ease;
}

.bar-label {
    font-size: 0.52rem;
    color: #94a3b8;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    writing-mode: vertical-lr;
    transform: rotate(180deg);
    height: 30px;
}

/* === SALARY BARS === */
.salary-bar-wrap {
    margin-bottom: 0.75rem;
}

.salary-bar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.65rem;
    font-weight: 700;
    margin-bottom: 0.3rem;
}

.salary-bar-track {
    height: 10px;
    background: #f1f5f9;
    border-radius: 9999px;
    overflow: hidden;
}

.salary-bar-fill {
    height: 100%;
    border-radius: 9999px;
}

.salary-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
    margin-top: 0.75rem;
}

.salary-card {
    border-radius: 0.75rem;
    padding: 0.75rem;
}

.salary-card-badge {
    font-size: 0.55rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    padding: 0.1rem 0.4rem;
    border-radius: 9999px;
    display: inline-block;
    margin-bottom: 0.3rem;
}

.salary-card-amount {
    font-size: 1.2rem;
    font-weight: 900;
    line-height: 1.1;
}

.salary-card-sub {
    font-size: 0.58rem;
    margin-top: 0.2rem;
    opacity: 0.75;
}
</style>

@php
$kpis = $kpis ?? [];
$genderStats = $kpis['genderStats'] ?? ['total'=>0,'femme'=>0,'homme'=>0,'pct_femme'=>50,'pct_homme'=>50];
$totalBc = $kpis['totalBc'] ?? 0;
$totalWc = $kpis['totalWc'] ?? 0;
$totalBcPct = $kpis['totalBcPct'] ?? 0;
$totalWcPct = $kpis['totalWcPct'] ?? 0;
$siteStats = $kpis['siteStats'] ?? [];
$sourcingStats = $kpis['sourcingStats'] ?? [];
$acquisitionStats = $kpis['acquisitionStats'] ?? [];
$totalAcquisition = $kpis['totalAcquisition'] ?? 1;
$tauxCloture = $kpis['tauxCloture'] ?? [];
$totalPostes = $kpis['totalPostes'] ?? 0;
$statutPostes = $kpis['statutPostes'] ?? ['total'=>0,'pas_encore'=>0,'en_cours'=>0,'cloture'=>0];
@endphp

<div class="db-wrap">
    <h1 class="db-title">Dashboard RH — {{ \App\Models\Company::find(session('active_company_id'))?->name ?? 'Safari' }}</h1>

    {{-- ====== 4 KPI STATUS CARDS (Synchro TB Recrutement) ====== --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.75rem;margin-bottom:1.25rem">

        {{-- Total --}}
        <div style="background:#1e293b;border-radius:1.25rem;padding:1.25rem;color:white;display:flex;flex-direction:column;gap:0.5rem;box-shadow:0 4px 20px -4px rgba(30,41,59,0.3)">
            <div style="font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8">Total Postes</div>
            <div style="font-size:2.5rem;font-weight:900;line-height:1">{{ $statutPostes['total'] }}</div>
            <div style="font-size:0.65rem;color:#64748b;font-weight:600">TB Recrutement complet</div>
        </div>

        {{-- Pas encore lancé --}}
        <div style="background:white;border-radius:1.25rem;padding:1.25rem;display:flex;flex-direction:column;gap:0.5rem;box-shadow:0 2px 12px -2px rgba(0,0,0,0.06);border-left:4px solid #94a3b8">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div style="font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8">Pas encore lancé</div>
                <div style="width:8px;height:8px;border-radius:50%;background:#94a3b8"></div>
            </div>
            <div style="font-size:2.5rem;font-weight:900;line-height:1;color:#334155">{{ $statutPostes['pas_encore'] }}</div>
            <div style="font-size:0.62rem;color:#94a3b8;font-weight:600">En attente de publication</div>
        </div>

        {{-- En cours --}}
        <div style="background:white;border-radius:1.25rem;padding:1.25rem;display:flex;flex-direction:column;gap:0.5rem;box-shadow:0 2px 12px -2px rgba(0,0,0,0.06);border-left:4px solid #F59E0B">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div style="font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#F59E0B">En cours</div>
                <div style="width:8px;height:8px;border-radius:50%;background:#F59E0B"></div>
            </div>
            <div style="font-size:2.5rem;font-weight:900;line-height:1;color:#92400e">{{ $statutPostes['en_cours'] }}</div>
            <div style="font-size:0.62rem;color:#94a3b8;font-weight:600">Offre publiée · candidatures ouvertes</div>
        </div>

        {{-- Clôturé --}}
        <div style="background:white;border-radius:1.25rem;padding:1.25rem;display:flex;flex-direction:column;gap:0.5rem;box-shadow:0 2px 12px -2px rgba(0,0,0,0.06);border-left:4px solid #10B981">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div style="font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#10B981">Clôturé</div>
                <div style="width:8px;height:8px;border-radius:50%;background:#10B981"></div>
            </div>
            <div style="font-size:2.5rem;font-weight:900;line-height:1;color:#065f46">{{ $statutPostes['cloture'] }}</div>
            <div style="font-size:0.62rem;color:#94a3b8;font-weight:600">Candidat recruté · poste pourvu</div>
        </div>

    </div>

    <div class="db-grid">

        {{-- ===================== GENRE ===================== --}}
        <div class="db-card span-1">
            <div class="db-card-title">Répartition Genre</div>
            <div class="db-card-sub">Total : {{ $genderStats['total'] }} clôtures</div>
            <div class="gender-row">
                {{-- FEMME --}}
                <div class="gender-item">
                    <div class="gender-figure" style="display:flex;justify-content:center;align-items:center; position:relative; width:80px; height:130px; filter: drop-shadow(0 0 10px rgba(236, 72, 153, 0.2));">
                        
                        {{-- Background Stroke (pink outline) --}}
                        <svg style="position:absolute; inset:0; width:100%; height:100%; z-index:2; pointer-events:none;" viewBox="0 0 60 100">
                            <g fill="none" stroke="#F472B6" stroke-width="2" stroke-linejoin="round">
                                <circle cx="30" cy="14" r="9"/>
                                <path d="M 40 26 L 20 26 C 16 26 13 29 12 33 L 6 74 C 5 77 7 80 10 80 L 22 80 L 22 97 C 22 99 23 100 25 100 L 28 100 C 29 100 30 99 30 97 L 30 80 L 34 80 L 34 97 C 34 99 35 100 37 100 L 40 100 C 41 100 42 99 42 97 L 42 80 L 50 80 C 53 80 55 77 54 74 L 48 33 C 47 29 44 26 40 26 Z"/>
                            </g>
                        </svg>

                        {{-- Fill (pink liquid inside) --}}
                        <svg style="position:absolute; inset:0; width:100%; height:100%; z-index:1;" viewBox="0 0 60 100">
                            <defs>
                                <clipPath id="female-clip">
                                    <circle cx="30" cy="14" r="9"/>
                                    <path d="M 40 26 L 20 26 C 16 26 13 29 12 33 L 6 74 C 5 77 7 80 10 80 L 22 80 L 22 97 C 22 99 23 100 25 100 L 28 100 C 29 100 30 99 30 97 L 30 80 L 34 80 L 34 97 C 34 99 35 100 37 100 L 40 100 C 41 100 42 99 42 97 L 42 80 L 50 80 C 53 80 55 77 54 74 L 48 33 C 47 29 44 26 40 26 Z"/>
                                </clipPath>
                            </defs>
                            {{-- White background --}}
                            <rect x="0" y="0" width="60" height="100" fill="#ffffff" clip-path="url(#female-clip)" />
                            {{-- Pink Liquid --}}
                            <rect x="0" y="{{ 100 - $genderStats['pct_femme'] }}" width="60" height="100" fill="#F472B6" clip-path="url(#female-clip)" />
                        </svg>

                        {{-- Percentage Overlay --}}
                        <div style="position:absolute; top:50%; left:0; right:0; transform:translateY(-50%); text-align:center; z-index:3;">
                            <span style="font-size:1.5rem; font-weight:900; color:#ffffff; -webkit-text-stroke: 1.5px #EC4899; text-shadow: 0 4px 6px rgba(236,72,153,0.3);">
                                {{ $genderStats['pct_femme'] }}%
                            </span>
                        </div>
                    </div>
                    <div class="gender-label mt-1">Féminin</div>
                    <div class="gender-badge">{{ $genderStats['femme'] }} / {{ $genderStats['total'] }}</div>
                </div>

                {{-- HOMME --}}
                <div class="gender-item">
                    <div class="gender-figure" style="display:flex;justify-content:center;align-items:center; position:relative; width:80px; height:130px; filter: drop-shadow(0 0 10px rgba(59, 130, 246, 0.2));">
                        
                        {{-- Background Stroke (blue outline) --}}
                        <svg style="position:absolute; inset:0; width:100%; height:100%; z-index:2; pointer-events:none;" viewBox="0 0 60 100">
                            <g fill="none" stroke="#60A5FA" stroke-width="2" stroke-linejoin="round">
                                <circle cx="30" cy="14" r="9"/>
                                <path d="M 40 26 L 20 26 C 13 26 8 31 8 38 L 8 64 C 8 66 10 68 12 68 L 18 68 L 18 97 C 18 99 19 100 21 100 L 26 100 C 28 100 28 99 28 97 L 28 68 L 32 68 L 32 97 C 32 99 32 100 34 100 L 39 100 C 41 100 42 99 42 97 L 42 68 L 48 68 C 50 68 52 66 52 64 L 52 38 C 52 31 47 26 40 26 Z"/>
                            </g>
                        </svg>

                        {{-- Fill (blue liquid inside) --}}
                        <svg style="position:absolute; inset:0; width:100%; height:100%; z-index:1;" viewBox="0 0 60 100">
                            <defs>
                                <clipPath id="male-clip">
                                    <circle cx="30" cy="14" r="9"/>
                                    <path d="M 40 26 L 20 26 C 13 26 8 31 8 38 L 8 64 C 8 66 10 68 12 68 L 18 68 L 18 97 C 18 99 19 100 21 100 L 26 100 C 28 100 28 99 28 97 L 28 68 L 32 68 L 32 97 C 32 99 32 100 34 100 L 39 100 C 41 100 42 99 42 97 L 42 68 L 48 68 C 50 68 52 66 52 64 L 52 38 C 52 31 47 26 40 26 Z"/>
                                </clipPath>
                            </defs>
                            {{-- White background --}}
                            <rect x="0" y="0" width="60" height="100" fill="#ffffff" clip-path="url(#male-clip)" />
                            {{-- Blue Liquid --}}
                            <rect x="0" y="{{ 100 - $genderStats['pct_homme'] }}" width="60" height="100" fill="#3B82F6" clip-path="url(#male-clip)" />
                        </svg>

                        {{-- Percentage Overlay --}}
                        <div style="position:absolute; top:50%; left:0; right:0; transform:translateY(-50%); text-align:center; z-index:3;">
                            <span style="font-size:1.5rem; font-weight:900; color:#ffffff; -webkit-text-stroke: 1.5px #3B82F6; text-shadow: 0 4px 6px rgba(59,130,246,0.3);">
                                {{ $genderStats['pct_homme'] }}%
                            </span>
                        </div>
                    </div>
                    <div class="gender-label mt-1">Masculin</div>
                    <div class="gender-badge">{{ $genderStats['homme'] }} / {{ $genderStats['total'] }}</div>
                </div>
            </div>
        </div>

        {{-- ===================== SITES MAP ===================== --}}
        <div class="db-card span-1">
            <div class="db-card-title">Répartition par Site</div>
            <div class="db-card-sub">Volumes de recrutement</div>
            @foreach($siteStats as $site => $data)
            <div class="site-row">
                <div class="site-dot" style="background:{{ $data['color'] }}"></div>
                <div class="site-name">{{ $site }}</div>
                <div class="site-count">{{ $data['count'] }}</div>
            </div>
            @endforeach
        </div>



        {{-- ===================== BC/WC DONUT ===================== --}}
        <div class="db-card span-1">
            <div class="db-card-title">Statut Poste</div>
            <div class="db-card-sub">Blue Collar vs White Collar</div>
            <div class="donut-wrap">
                @php
                    $bcDeg = round($totalBcPct * 3.6);
                    $wcDeg = 360 - $bcDeg;
                    // SVG donut stroke values
                    $r = 42; $circ = round(2 * M_PI * $r, 2);
                    $bcStroke = round($totalBcPct / 100 * $circ, 2);
                    $wcStroke = round($totalWcPct / 100 * $circ, 2);
                @endphp
                <svg class="donut-svg" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="#f1f5f9" stroke-width="14"/>
                    <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="#3B82F6" stroke-width="14"
                        stroke-dasharray="{{ $bcStroke }} {{ $circ - $bcStroke }}"
                        stroke-dashoffset="{{ round($circ / 4, 2) }}" stroke-linecap="round"/>
                    <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="#10B981" stroke-width="14"
                        stroke-dasharray="{{ $wcStroke }} {{ $circ - $wcStroke }}"
                        stroke-dashoffset="{{ round($circ / 4 - $bcStroke, 2) }}" stroke-linecap="round"/>
                    <text x="50" y="46" text-anchor="middle" font-size="14" font-weight="900" fill="#1e293b">{{ $totalBc + $totalWc }}</text>
                    <text x="50" y="58" text-anchor="middle" font-size="7" fill="#94a3b8" font-weight="600">TOTAL</text>
                </svg>

                <div class="donut-legend" style="width:100%">
                    <div class="legend-row">
                        <div class="legend-left"><span class="legend-dot" style="background:#3B82F6"></span>Blue Collar (BC)</div>
                        <div class="legend-right">{{ $totalBc }} <span class="legend-badge" style="background:#eff6ff;color:#3B82F6">{{ $totalBcPct }}%</span></div>
                    </div>
                    <div class="legend-row">
                        <div class="legend-left"><span class="legend-dot" style="background:#10B981"></span>White Collar (WC)</div>
                        <div class="legend-right">{{ $totalWc }} <span class="legend-badge" style="background:#ecfdf5;color:#10B981">{{ $totalWcPct }}%</span></div>
                    </div>
                </div>
            </div>
        </div>



        {{-- ===================== CANAL D'ACQUISITION ===================== --}}
        <div class="db-card span-1">
            <div class="db-card-title">Canal d'Acquisition</div>
            <div class="db-card-sub">Mobilité · Externe · Création · Spontanée</div>
            <div class="donut-wrap">
                @php
                    $acqData = [
                        ['label'=>'Mobilité interne','count'=>$acquisitionStats['interne']??0,'color'=>'#F59E0B'],
                        ['label'=>'Ext. sourcing','count'=>$acquisitionStats['externe']??0,'color'=>'#10B981'],
                        ['label'=>'Création poste','count'=>$acquisitionStats['creation']??0,'color'=>'#A855F7'],
                        ['label'=>'Spontanée','count'=>$acquisitionStats['spontanee']??0,'color'=>'#3B82F6'],
                    ];
                    $total = max(1, $totalAcquisition);
                    // Build conic gradient
                    $conic = '';
                    $offset = 0;
                    foreach($acqData as $a) {
                        $pct = round($a['count'] / $total * 100);
                        $conic .= $a['color'].' '.$offset.'% '.($offset+$pct).'%, ';
                        $offset += $pct;
                    }
                    $conic = rtrim($conic, ', ');
                @endphp
                <div style="position:relative;width:110px;height:110px;flex-shrink:0">
                    <div style="width:110px;height:110px;border-radius:50%;background:conic-gradient({{ $conic }})"></div>
                    <div style="position:absolute;inset:18px;background:white;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center">
                        <span style="font-size:1.3rem;font-weight:900;color:#1e293b;line-height:1">{{ $total }}</span>
                        <span style="font-size:0.55rem;font-weight:700;color:#94a3b8;text-transform:uppercase">Total</span>
                    </div>
                </div>
                <div class="donut-legend" style="width:100%">
                    @foreach($acqData as $a)
                    <div class="legend-row">
                        <div class="legend-left"><span class="legend-dot" style="background:{{ $a['color'] }}"></span>{{ $a['label'] }}</div>
                        <div class="legend-right">{{ $a['count'] }} <span class="legend-badge" style="background:#f8fafc;color:{{ $a['color'] }}">{{ round($a['count']/$total*100) }}%</span></div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ===================== TAUX DE CLOTURE ===================== --}}
        <div class="db-card span-2">
            <div class="db-card-title">Taux de Clôture par Direction</div>
            <div class="db-card-sub">Postes clôturés vs total</div>
            <div class="bar-chart">
                @foreach($tauxCloture as $dir => $taux)
                @php
                    $barColor = $dir === 'Global' ? '#3B82F6' : 'hsl('.max(0, 120 - $taux * 1.2).', 70%, 48%)';
                @endphp
                <div class="bar-item">
                    <span style="font-size:0.58rem;font-weight:800;color:{{ $barColor }};margin-bottom:2px">{{ $taux }}%</span>
                    <div class="bar-fill" style="height:{{ max(2, $taux) }}%;background:{{ $barColor }}"></div>
                    <span class="bar-label">{{ $dir }}</span>
                </div>
                @endforeach
            </div>
        </div>



    </div>
</div>

@endif
</x-shell-layout>
