<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TechAssessmentRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Application $application)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Évaluation technique requise')
            ->greeting('Bonjour ' . ($notifiable->profile->first_name ?? ''))
            ->line('Le candidat **' . ($this->application->candidate->full_name ?? 'Un candidat') . '** a atteint la phase d\'entretien pour le poste **' . ($this->application->job->title ?? '') . '**.')
            ->line('Il n\'a pas encore d\'évaluation technique (SJT) assignée.')
            ->action('Générer l\'évaluation', route('jobs.show', ['job' => $this->application->job_id, 'company_id' => $this->application->company_id, 'tab' => 'analysis']))
            ->line('Merci d\'utiliser notre application !');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'job_id' => $this->application->job_id,
            'message' => 'Évaluation technique requise pour ' . ($this->application->candidate->full_name ?? 'un candidat'),
        ];
    }
}
