<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ApplicationAcknowledgementMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $candidateName,
        public string $jobTitle,
        public string $companyName,
        public string $applicationReference
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('career.mail.ack_subject', ['job' => $this->jobTitle]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.application-acknowledgement',
        );
    }
}
