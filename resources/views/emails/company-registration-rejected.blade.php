<p>{{ __('platform.mail.greeting', ['name' => $recipientName]) }}</p>
<p>{{ __('platform.mail.rejected_body', ['company' => $company->name]) }}</p>
<p>{{ __('platform.mail.rejected_reason_label') }}</p>
<p>{{ $rejectionReason }}</p>
<p>{{ __('platform.mail.rejected_next_step') }}</p>
