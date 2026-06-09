<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyRegistrationRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly string $recipientName,
        public readonly string $rejectionReason,
        public readonly string $mailLocale
    ) {
    }

    public function envelope(): Envelope
    {
        app()->setLocale($this->mailLocale);

        return new Envelope(
            subject: __('platform.mail.rejected_subject', ['company' => $this->company->name])
        );
    }

    public function content(): Content
    {
        app()->setLocale($this->mailLocale);

        return new Content(
            view: 'emails.company-registration-rejected'
        );
    }
}
