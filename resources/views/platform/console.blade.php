<x-shell-layout :title="__('ui.nav.platform_console').' | '.config('app.name')">
    <div
        x-data="{
            trend: @js($registrationTrend),
            selectedSeries: 'total',
            seriesMeta: {
                total: { label: 'Total', color: '#312e81' },
                approved: { label: 'Approved', color: '#0f766e' },
                pending: { label: 'Pending', color: '#6d28d9' },
                rejected: { label: 'Rejected', color: '#be123c' },
            },
            chartWidth: 620,
            chartHeight: 240,
            chartPaddingX: 30,
            chartPaddingY: 24,
            get maxValue() {
                const values = this.trend.map((item) => Number(item[this.selectedSeries] ?? 0));
                const max = Math.max(...values, 1);
                return max;
            },
            pointX(index) {
                if (this.trend.length <= 1) {
                    return this.chartWidth / 2;
                }
                const usable = this.chartWidth - (this.chartPaddingX * 2);
                return this.chartPaddingX + ((usable / (this.trend.length - 1)) * index);
            },
            pointY(value) {
                const usable = this.chartHeight - (this.chartPaddingY * 2);
                return this.chartHeight - this.chartPaddingY - ((Number(value) / this.maxValue) * usable);
            },
            get points() {
                return this.trend
                    .map((item, index) => `${this.pointX(index)},${this.pointY(item[this.selectedSeries] ?? 0)}`)
                    .join(' ');
            },
            get areaPoints() {
                if (this.trend.length === 0) {
                    return '';
                }
                const first = `${this.pointX(0)},${this.chartHeight - this.chartPaddingY}`;
                const last = `${this.pointX(this.trend.length - 1)},${this.chartHeight - this.chartPaddingY}`;
                return `${first} ${this.points} ${last}`;
            },
        }"
        class="space-y-6"
    >
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="panel-title text-3xl font-semibold tracking-tight text-slate-900">{{ __('ui.nav.platform_console') }}</h1>
                    <p class="mt-1 text-sm text-slate-600">{{ __('platform.console_subtitle') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('platform.company-approvals', ['status' => 'pending']) }}" class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-sm font-semibold text-primary-900 transition hover:bg-primary-100">
                        Review pending
                    </a>
                    <a href="{{ route('superadmin.contact-inquiries.index') }}" class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-sm font-semibold text-primary-900 transition hover:bg-primary-100">
                        {{ __('ui.nav.contact_inquiries') }}
                    </a>
                    <a href="{{ route('platform.ai-diagnostics') }}" class="rounded-xl border border-aura-200 bg-aura-50 px-3 py-2 text-sm font-semibold text-aura-800 transition hover:bg-aura-100">
                        AI diagnostics
                    </a>
                </div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <a href="{{ route('platform.company-approvals', ['status' => 'all']) }}" class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-4 transition hover:border-slate-300">
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('platform.total_companies') }}</p>
                    <p class="kpi-value mt-2 text-4xl font-semibold text-slate-900">{{ number_format($totalCompanies) }}</p>
                </a>
                <a href="{{ route('platform.company-approvals', ['status' => 'approved']) }}" class="rounded-2xl border border-success-200 bg-gradient-to-br from-success-50 to-white p-4 transition hover:border-success-300">
                    <p class="text-xs uppercase tracking-wider text-success-700">{{ __('platform.active_companies') }}</p>
                    <p class="kpi-value mt-2 text-4xl font-semibold text-success-800">{{ number_format($activeCompanies) }}</p>
                </a>
                <a href="{{ route('platform.company-approvals', ['status' => 'pending']) }}" class="rounded-2xl border border-primary-200 bg-gradient-to-br from-primary-50 to-white p-4 transition hover:border-primary-300">
                    <p class="text-xs uppercase tracking-wider text-primary-700">{{ __('platform.pending_companies') }}</p>
                    <p class="kpi-value mt-2 text-4xl font-semibold text-primary-800">{{ number_format($pendingCompanies) }}</p>
                </a>
                <a href="{{ route('platform.company-approvals', ['status' => 'rejected']) }}" class="rounded-2xl border border-danger-200 bg-gradient-to-br from-danger-50 to-white p-4 transition hover:border-danger-300">
                    <p class="text-xs uppercase tracking-wider text-danger-700">Rejected</p>
                    <p class="kpi-value mt-2 text-4xl font-semibold text-danger-800">{{ number_format($rejectedCompanies) }}</p>
                </a>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[2fr_1fr]">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-900">Registration Trend</h2>
                        <p class="text-sm text-slate-600">Last 6 months company registration activity.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(meta, key) in seriesMeta" :key="key">
                            <button
                                type="button"
                                @click="selectedSeries = key"
                                :class="selectedSeries === key ? 'border-transparent text-white' : 'border-slate-200 bg-white text-slate-700'"
                                :style="selectedSeries === key ? `background-color: ${meta.color}` : ''"
                                class="rounded-full border px-3 py-1 text-xs font-semibold transition"
                                x-text="meta.label"
                            ></button>
                        </template>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <svg viewBox="0 0 620 240" class="min-w-[620px]">
                        <line x1="30" y1="216" x2="590" y2="216" stroke="#d1d5db" stroke-width="1" />
                        <line x1="30" y1="24" x2="30" y2="216" stroke="#d1d5db" stroke-width="1" />

                        <template x-if="trend.length > 0">
                            <polygon :points="areaPoints" :fill="seriesMeta[selectedSeries].color + '22'"></polygon>
                        </template>
                        <template x-if="trend.length > 0">
                            <polyline :points="points" fill="none" :stroke="seriesMeta[selectedSeries].color" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline>
                        </template>

                        <template x-for="(item, index) in trend" :key="item.label">
                            <g>
                                <circle :cx="pointX(index)" :cy="pointY(item[selectedSeries] ?? 0)" r="4.5" :fill="seriesMeta[selectedSeries].color"></circle>
                                <text :x="pointX(index)" y="234" text-anchor="middle" class="fill-slate-500 text-[10px]" x-text="item.label"></text>
                            </g>
                        </template>
                    </svg>
                </div>
            </section>

            <section class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-slate-900">Approval Health</h2>
                    <p class="mt-1 text-sm text-slate-600">Current approval conversion from requests to active companies.</p>
                    <div class="mt-4 rounded-2xl border border-success-200 bg-success-50 p-4">
                        <p class="text-xs uppercase tracking-wider text-success-700">Approval rate</p>
                        <p class="mt-2 text-4xl font-semibold text-success-800">{{ number_format((float) $approvalRate, 1) }}%</p>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-slate-900">AI Processing Status</h2>
                    <p class="mt-1 text-sm text-slate-600">Current request volume by AI job state.</p>
                    <div class="mt-4 space-y-3">
                        @php
                            $aiTotal = max(1, array_sum($aiStatusCounts));
                            $aiBars = [
                                ['label' => 'Queued', 'key' => 'queued', 'color' => 'bg-slate-500'],
                                ['label' => 'Running', 'key' => 'running', 'color' => 'bg-primary-500'],
                                ['label' => 'Succeeded', 'key' => 'succeeded', 'color' => 'bg-success-500'],
                                ['label' => 'Failed', 'key' => 'failed', 'color' => 'bg-danger-500'],
                            ];
                        @endphp
                        @foreach ($aiBars as $bar)
                            @php
                                $value = (int) ($aiStatusCounts[$bar['key']] ?? 0);
                                $width = round(($value / $aiTotal) * 100, 1);
                            @endphp
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs font-semibold text-slate-700">
                                    <span>{{ $bar['label'] }}</span>
                                    <span>{{ number_format($value) }}</span>
                                </div>
                                <div class="h-2.5 rounded-full bg-slate-100">
                                    <div class="h-2.5 rounded-full {{ $bar['color'] }}" style="width: {{ $width }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-slate-900">Recent Approvals</h2>
                <a href="{{ route('platform.company-approvals', ['status' => 'approved']) }}" class="text-sm font-semibold text-aura-700 hover:text-aura-900">View all</a>
            </div>

            @if ($recentApprovals->isEmpty())
                <p class="mt-4 text-sm text-slate-600">No approved companies yet.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Company</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Admin</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Approved at</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentApprovals as $request)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-4 py-3">
                                        @if ($request->company !== null)
                                            <a href="{{ route('platform.company-approvals.show', $request->company) }}" class="font-semibold text-slate-900 hover:text-aura-700">
                                                {{ $request->company->name }}
                                            </a>
                                        @else
                                            <span class="font-semibold text-slate-700">Unknown company</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">
                                        {{ $request->requestedBy?->profile?->full_name ?? $request->requestedBy?->email ?? 'Unknown' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ optional($request->reviewed_at)->format('M d, Y H:i') ?? 'N/A' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-shell-layout>
