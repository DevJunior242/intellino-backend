<?php

namespace App\Notifications;

use App\Models\Examen;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ExamenCanceled extends Notification implements ShouldQueue
{
    use Queueable;
    public Examen $examen;

    /**
     * Create a new notification instance.
     */
    public function __construct($examen)
    {
        $this->examen = $examen;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $clubId = optional($this->examen)->club_id;
        $url = config('app.frontend_url') . "/dashboard/examen/{$this->examen->id}/show?club_id={$clubId}";
        return (new MailMessage)
            ->line('Salut ' . $notifiable->fullname)
            ->line('Le examen du ' . $this->examen->start_date . ' a été annulé.')
            ->action('Voir les détails de l\'examen', $url)
            ->line('Merci d\'utiliser Intellino !');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $clubId = optional($this->examen)->club_id;
        return [
            'title' => 'Examen annulé',
            'message' => 'Le examen du ' . $this->examen->start_date . ' a été annulé.',
            'url' => "/dashboard/student/{$this->examen->id}/candidates"
        ];
    }
}
