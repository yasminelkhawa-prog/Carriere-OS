<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyRegistrationApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly string $recipientName,
        public readonly string $mailLocale,
        public readonly ?string $verificationUrl = null
    ) {
    }

    public function envelope(): Envelope
    {
        app()->setLocale($this->mailLocale);

        return new Envelope(
            subject: __('platform.mail.approved_subject', ['company' => $this->company->name])
        );
    }

    public function content(): Content
    {
        app()->setLocale($this->mailLocale);

        return new Content(
            view: 'emails.company-registration-approved'
        );
    }
}
