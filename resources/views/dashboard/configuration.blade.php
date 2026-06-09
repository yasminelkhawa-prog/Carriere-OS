<x-shell-layout :title="$title.' | '.config('app.name')">
    <div class="space-y-6">
        @php
            $linkedinPartnerSettings = (array) data_get($linkedinPartnerReadiness ?? [], 'settings', []);
            $linkedinPartnerMissing = (array) data_get($linkedinPartnerReadiness ?? [], 'missing', []);
            $linkedinPartnerReady = (bool) data_get($linkedinPartnerReadiness ?? [], 'ready', false);
            $linkedinPartnerMeta = (array) data_get($linkedinIntegration?->meta_json, 'partner_job_posting', []);
            $latestLinkedInAttempt = $latestLinkedInPosting?->publishAttempts->first();
            $latestLinkedInAttemptDiagnostics = (array) ($latestLinkedInAttempt?->diagnostics_json ?? []);
            $latestLinkedInAttemptErrorPayload = (array) ($latestLinkedInAttempt?->error_payload_json ?? []);
        @endphp
        <x-glass-card :title="$title" :subtitle="$description">
            <div class="grid gap-4 lg:grid-cols-2">
                <article class="rounded-2xl border border-white/80 bg-white/75 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-aura-700/85">{{ __('ui.integrations.linkedin.eyebrow') }}</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ __('ui.integrations.linkedin.title') }}</h3>
                            <p class="mt-2 text-sm text-slate-700">{{ __('ui.integrations.linkedin.description') }}</p>
                        </div>
                        @php
                            $status = (string) ($linkedinIntegration?->status ?? \App\Models\CompanyIntegration::STATUS_DISCONNECTED);
                            $statusClasses = [
                                \App\Models\CompanyIntegration::STATUS_CONNECTED => 'border-success-200 bg-success-100 text-success-900',
                                \App\Models\CompanyIntegration::STATUS_PENDING => 'border-primary-200 bg-primary-100 text-primary-900',
                                \App\Models\CompanyIntegration::STATUS_ERROR => 'border-danger-200 bg-danger-100 text-danger-900',
                                \App\Models\CompanyIntegration::STATUS_EXPIRED => 'border-danger-200 bg-danger-100 text-danger-900',
                                \App\Models\CompanyIntegration::STATUS_DISCONNECTED => 'border-slate-200 bg-slate-100 text-slate-700',
                            ];
                        @endphp
                        <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$status] ?? $statusClasses[\App\Models\CompanyIntegration::STATUS_DISCONNECTED] }}">
                            {{ __('ui.integrations.statuses.'.$status) }}
                        </span>
                    </div>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.integrations.linkedin.company_label') }}</dt>
                            <dd class="mt-1 text-slate-900">{{ $company->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.integrations.linkedin.account_label') }}</dt>
                            <dd class="mt-1 text-slate-900">{{ $linkedinIntegration?->external_account_name ?: __('ui.integrations.linkedin.not_connected') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.integrations.linkedin.connected_at_label') }}</dt>
                            <dd class="mt-1 text-slate-900">{{ $linkedinIntegration?->last_connected_at?->diffForHumans() ?? __('ui.integrations.linkedin.not_connected') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.integrations.linkedin.last_used_at_label') }}</dt>
                            <dd class="mt-1 text-slate-900">{{ $linkedinIntegration?->last_used_at?->diffForHumans() ?? __('ui.integrations.linkedin.not_used_yet') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.integrations.linkedin.scopes_label') }}</dt>
                            <dd class="mt-1 text-slate-900">
                                @if(!empty($linkedinIntegration?->granted_scopes_json))
                                    {{ implode(', ', (array) $linkedinIntegration->granted_scopes_json) }}
                                @else
                                    {{ __('ui.integrations.linkedin.not_connected') }}
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if(! $linkedinConfigured)
                        <div class="mt-4 rounded-xl border border-danger-200 bg-danger-50 p-3 text-sm text-danger-900">
                            {{ __('ui.integrations.linkedin.not_configured') }}
                        </div>
                    @endif

                    @if($linkedinIntegration?->last_error)
                        <div class="mt-4 rounded-xl border border-danger-200 bg-danger-50 p-3 text-sm text-danger-900">
                            {{ $linkedinIntegration->last_error }}
                        </div>
                    @endif

                    <div class="mt-5 flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('admin.integrations.linkedin.connect', ['company_id' => $company->id]) }}">
                            @csrf
                            <button type="submit" @disabled(! $linkedinConfigured) class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                                {{ $linkedinIntegration?->isConnected() ? __('ui.integrations.linkedin.reconnect') : __('ui.integrations.linkedin.connect') }}
                            </button>
                        </form>

                        @if($linkedinIntegration)
                            <form method="POST" action="{{ route('admin.integrations.linkedin.test', ['company_id' => $company->id]) }}">
                                @csrf
                                <button type="submit" @disabled(! $linkedinIntegration->isConnected()) class="rounded-xl border border-primary-300/60 bg-white px-4 py-2 text-sm font-medium text-primary-800 transition hover:bg-primary-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400">
                                    {{ __('ui.integrations.linkedin.test_connection') }}
                                </button>
                            </form>
                        @endif

                        @if($linkedinIntegration)
                            <form method="POST" action="{{ route('admin.integrations.linkedin.disconnect', ['company_id' => $company->id]) }}">
                                @csrf
                                <button type="submit" class="rounded-xl border border-danger-300/60 bg-white px-4 py-2 text-sm font-medium text-danger-800 transition hover:bg-danger-50">
                                    {{ __('ui.integrations.linkedin.disconnect') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </article>

                <article class="rounded-2xl border border-white/80 bg-white/75 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-aura-700/85">{{ __('ui.integrations.linkedin.requirements_eyebrow') }}</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ __('ui.integrations.linkedin.requirements_title') }}</h3>
                    <p class="mt-2 text-sm text-slate-700">{{ __('ui.integrations.linkedin.requirements_description') }}</p>

                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li>{{ __('ui.integrations.linkedin.requirements_items.client_id') }}</li>
                        <li>{{ __('ui.integrations.linkedin.requirements_items.client_secret') }}</li>
                        <li>{{ __('ui.integrations.linkedin.requirements_items.redirect_uri') }}</li>
                        <li>{{ __('ui.integrations.linkedin.requirements_items.scopes') }}</li>
                    </ul>
                </article>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
                <article class="rounded-2xl border border-white/80 bg-white/75 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-aura-700/85">{{ __('ui.integrations.linkedin.partner_eyebrow') }}</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ __('ui.integrations.linkedin.partner_title') }}</h3>
                            <p class="mt-2 text-sm text-slate-700">{{ __('ui.integrations.linkedin.partner_description') }}</p>
                        </div>
                        <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $linkedinPartnerReady ? 'border-success-200 bg-success-100 text-success-900' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
                            {{ $linkedinPartnerReady ? __('ui.integrations.linkedin.partner_ready') : __('ui.integrations.linkedin.partner_incomplete') }}
                        </span>
                    </div>

                    @if(! $linkedinPartnerConfigured)
                        <div class="mt-4 rounded-xl border border-danger-200 bg-danger-50 p-3 text-sm text-danger-900">
                            {{ __('ui.integrations.linkedin.partner_runtime_not_configured') }}
                        </div>
                    @endif

                    @if($linkedinPartnerMissing !== [])
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                            {{ __('ui.integrations.linkedin.partner_missing_fields') }} {{ implode(', ', $linkedinPartnerMissing) }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.integrations.linkedin.partner-settings', ['company_id' => $company->id]) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                        @csrf

                        <x-form-field :label="__('ui.integrations.linkedin.partner_fields.partner_client_id')" name="partner_client_id">
                            <input type="text" name="partner_client_id" value="{{ old('partner_client_id', $linkedinPartnerSettings['partner_client_id'] ?? '') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        </x-form-field>

                        <x-form-field :label="__('ui.integrations.linkedin.partner_fields.partner_client_secret')" name="partner_client_secret">
                            <input type="text" name="partner_client_secret" value="{{ old('partner_client_secret', $linkedinPartnerSettings['partner_client_secret'] ?? '') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        </x-form-field>

                        <x-form-field :label="__('ui.integrations.linkedin.partner_fields.company_urn')" name="company_urn">
                            <input type="text" name="company_urn" value="{{ old('company_urn', $linkedinPartnerSettings['company_urn'] ?? '') }}" placeholder="urn:li:company:123456" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        </x-form-field>

                        <x-form-field :label="__('ui.integrations.linkedin.partner_fields.integration_context')" name="integration_context">
                            <input type="text" name="integration_context" value="{{ old('integration_context', $linkedinPartnerSettings['integration_context'] ?? '') }}" placeholder="urn:li:organization:123456" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        </x-form-field>

                        <x-form-field :label="__('ui.integrations.linkedin.partner_fields.contract_urn')" name="contract_urn">
                            <input type="text" name="contract_urn" value="{{ old('contract_urn', $linkedinPartnerSettings['contract_urn'] ?? '') }}" placeholder="urn:li:contract:123456" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        </x-form-field>

                        <x-form-field :label="__('ui.integrations.linkedin.partner_fields.developer_application_id')" name="developer_application_id">
                            <input type="text" name="developer_application_id" value="{{ old('developer_application_id', $linkedinPartnerSettings['developer_application_id'] ?? '') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        </x-form-field>

                        <x-form-field :label="__('ui.integrations.linkedin.partner_fields.company_name_fallback')" name="company_name_fallback">
                            <input type="text" name="company_name_fallback" value="{{ old('company_name_fallback', $linkedinPartnerSettings['company_name_fallback'] ?? $company->name) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        </x-form-field>

                        <x-form-field :label="__('ui.integrations.linkedin.partner_fields.company_apply_url_override')" name="company_apply_url_override">
                            <input type="url" name="company_apply_url_override" value="{{ old('company_apply_url_override', $linkedinPartnerSettings['company_apply_url_override'] ?? '') }}" placeholder="https://example.com/careers/company-slug/apply/job-id" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        </x-form-field>

                        <x-form-field :label="__('ui.integrations.linkedin.partner_fields.listing_type')" name="listing_type">
                            <select name="listing_type" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                                @php($listingType = old('listing_type', $linkedinPartnerSettings['listing_type'] ?? 'BASIC'))
                                <option value="BASIC" @selected($listingType === 'BASIC')>BASIC</option>
                                <option value="PREMIUM" @selected($listingType === 'PREMIUM')>PREMIUM</option>
                            </select>
                        </x-form-field>

                        <x-form-field :label="__('ui.integrations.linkedin.partner_fields.poster_email')" name="poster_email">
                            <input type="email" name="poster_email" value="{{ old('poster_email', $linkedinPartnerSettings['poster_email'] ?? '') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        </x-form-field>

                        <x-form-field :label="__('ui.integrations.linkedin.partner_fields.availability')" name="availability">
                            <input type="text" name="availability" value="{{ old('availability', $linkedinPartnerSettings['availability'] ?? '') }}" placeholder="INTERNAL" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        </x-form-field>

                        <div class="md:col-span-2 flex justify-end">
                            <button type="submit" class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-700">
                                {{ __('ui.integrations.linkedin.save_partner_settings') }}
                            </button>
                        </div>
                    </form>
                </article>

                <article class="rounded-2xl border border-white/80 bg-white/75 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-aura-700/85">{{ __('ui.integrations.linkedin.partner_test_eyebrow') }}</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ __('ui.integrations.linkedin.partner_test_title') }}</h3>
                    <p class="mt-2 text-sm text-slate-700">{{ __('ui.integrations.linkedin.partner_test_description') }}</p>

                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li>{{ __('ui.integrations.linkedin.partner_test_steps.oauth') }}</li>
                        <li>{{ __('ui.integrations.linkedin.partner_test_steps.partner_fields') }}</li>
                        <li>{{ __('ui.integrations.linkedin.partner_test_steps.publish') }}</li>
                        <li>{{ __('ui.integrations.linkedin.partner_test_steps.search') }}</li>
                    </ul>

                    <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        <p class="font-semibold text-slate-900">{{ __('ui.integrations.linkedin.partner_test_limits_title') }}</p>
                        <p class="mt-2">{{ __('ui.integrations.linkedin.partner_test_limits_body') }}</p>
                    </div>
                </article>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-[0.95fr_1.05fr]">
                <article class="rounded-2xl border border-white/80 bg-white/75 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-aura-700/85">{{ __('ui.integrations.linkedin.diagnostics_eyebrow') }}</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ __('ui.integrations.linkedin.diagnostics_title') }}</h3>
                    <p class="mt-2 text-sm text-slate-700">{{ __('ui.integrations.linkedin.diagnostics_description') }}</p>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.integrations.linkedin.diagnostics_fields.last_submission_at') }}</dt>
                            <dd class="mt-1 text-slate-900">{{ data_get($linkedinPartnerMeta, 'last_submission_at') ?: __('ui.integrations.linkedin.diagnostics_not_available') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.integrations.linkedin.diagnostics_fields.last_submission_task_urn') }}</dt>
                            <dd class="mt-1 break-all text-slate-900">{{ data_get($linkedinPartnerMeta, 'last_submission_task_urn') ?: __('ui.integrations.linkedin.diagnostics_not_available') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.integrations.linkedin.diagnostics_fields.latest_job') }}</dt>
                            <dd class="mt-1 text-slate-900">{{ $latestLinkedInPosting?->job?->title ?: __('ui.integrations.linkedin.diagnostics_not_available') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.integrations.linkedin.diagnostics_fields.latest_posting_status') }}</dt>
                            <dd class="mt-1 text-slate-900">{{ $latestLinkedInPosting?->status ?: __('ui.integrations.linkedin.diagnostics_not_available') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.integrations.linkedin.diagnostics_fields.latest_attempt_status') }}</dt>
                            <dd class="mt-1 text-slate-900">{{ $latestLinkedInAttempt?->status ?: __('ui.integrations.linkedin.diagnostics_not_available') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.integrations.linkedin.diagnostics_fields.latest_task_status') }}</dt>
                            <dd class="mt-1 text-slate-900">{{ data_get($latestLinkedInAttemptDiagnostics, 'task_status') ?: __('ui.integrations.linkedin.diagnostics_not_available') }}</dd>
                        </div>
                    </dl>

                    @if($latestLinkedInPosting?->last_publish_error || $latestLinkedInAttempt?->error_message)
                        <div class="mt-4 rounded-xl border border-danger-200 bg-danger-50 p-3 text-sm text-danger-900">
                            <p class="font-semibold">{{ __('ui.integrations.linkedin.diagnostics_last_error') }}</p>
                            <p class="mt-1 break-words">{{ $latestLinkedInAttempt?->error_message ?: $latestLinkedInPosting?->last_publish_error }}</p>
                        </div>
                    @endif
                </article>

                <article class="rounded-2xl border border-white/80 bg-white/75 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-aura-700/85">{{ __('ui.integrations.linkedin.diagnostics_payload_eyebrow') }}</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ __('ui.integrations.linkedin.diagnostics_payload_title') }}</h3>
                    <p class="mt-2 text-sm text-slate-700">{{ __('ui.integrations.linkedin.diagnostics_payload_description') }}</p>

                    <div class="mt-4 space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.integrations.linkedin.diagnostics_fields.latest_payload') }}</p>
                            <pre class="mt-2 max-h-56 overflow-auto rounded-xl bg-slate-950/95 p-3 text-xs text-slate-100">{{ json_encode(data_get($latestLinkedInAttemptDiagnostics, 'payload', data_get($latestLinkedInAttemptErrorPayload, 'payload', [])), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}' }}</pre>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.integrations.linkedin.diagnostics_fields.latest_response') }}</p>
                            <pre class="mt-2 max-h-56 overflow-auto rounded-xl bg-slate-950/95 p-3 text-xs text-slate-100">{{ json_encode(data_get($latestLinkedInAttemptDiagnostics, 'response', data_get($latestLinkedInAttemptErrorPayload, 'response', data_get($linkedinPartnerMeta, 'last_submission_response', []))), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}' }}</pre>
                        </div>
                    </div>
                </article>
            </div>
        </x-glass-card>
    </div>
</x-shell-layout>
