<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InterviewConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $candidateName,
        public string $jobTitle,
        public string $scheduledForText,
        public string $channel,
        public ?string $meetingLink = null,
        public array $context = []
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('kanban.mail.interview_subject', ['job' => $this->jobTitle]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.interview-confirmation',
        );
    }
}
