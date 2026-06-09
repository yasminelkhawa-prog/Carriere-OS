<?php

namespace App\Listeners;

use App\Support\Audit\AuditActionType;
use App\Support\Audit\AuditLogger;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Auth;

class LogEmailSent
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function handle(MessageSent $event): void
    {
        $actor = Auth::user();

        if ($actor === null) {
            return;
        }

        $to = [];

        foreach ($event->message->getTo() as $address) {
            $to[] = $address->getAddress();
        }

        $this->auditLogger->log(
            actionType: AuditActionType::EMAIL_SENT,
            entityType: 'mail_message',
            entityId: null,
            metadata: [
                'to' => $to,
                'subject' => $event->message->getSubject(),
            ],
            actor: $actor
        );
    }
}
