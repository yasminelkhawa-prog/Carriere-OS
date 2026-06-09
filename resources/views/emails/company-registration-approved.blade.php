<p>{{ __('platform.mail.greeting', ['name' => $recipientName]) }}</p>
<p>{{ __('platform.mail.approved_body', ['company' => $company->name]) }}</p>
<p>{{ __('platform.mail.approved_next_step', ['url' => route('login')]) }}</p>
@if (! empty($verificationUrl))
    <p>{{ __('platform.mail.approved_verify_prompt') }}</p>
    <p><a href="{{ $verificationUrl }}">{{ __('platform.mail.approved_verify_link_label') }}</a></p>
@endif
