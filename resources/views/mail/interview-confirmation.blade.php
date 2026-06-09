@php
    $locationLabel = trim((string) ($context['location_label'] ?? __('interviews.fields.meeting_link')));
    $locationValue = trim((string) ($context['location_value'] ?? $meetingLink ?? ''));
@endphp

<p>{{ __('kanban.mail.greeting', ['name' => $candidateName]) }}</p>
<p>{{ __('kanban.mail.interview_line', ['job' => $jobTitle, 'datetime' => $scheduledForText, 'channel' => $channel]) }}</p>
@if($locationValue !== '')
    <p>{{ $locationLabel }}: {{ $locationValue }}</p>
@endif
<p>{{ __('kanban.mail.closing') }}</p>
