<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class RejectionDecisionMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $candidateName,
        public string $jobTitle,
        public string $draftBody
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('kanban.mail.rejection_subject', ['job' => $this->jobTitle]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.rejection-decision',
        );
    }
}
