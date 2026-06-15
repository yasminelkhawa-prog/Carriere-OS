<?php

namespace App\Mail;

use App\Models\PsyTest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PsyTestInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PsyTest $psyTest,
        public readonly string $testUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitation — Évaluation psychométrique',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.psy-test-invitation',
        );
    }
}
