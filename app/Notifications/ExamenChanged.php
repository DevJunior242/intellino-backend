<?php

namespace App\Notifications;

use App\Models\Examen;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ExamenChanged extends Notification implements ShouldQueue
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
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $clubId = optional($this->examen)->club_id;
        // http: //localhost:5173/dashboard/student/019db566-4be5-71a6-9629-a53a6ad10e8f/candidates
        $url = config('app.frontend_url') . "/dashboard/student/{$this->examen->id}/candidates";
        return (new MailMessage)
            ->line('Salut ' . $notifiable->fullname)
            ->line('Le examen du ' . $this->examen->old_start_date . ' a été reporté. au ' . $this->examen->start_date . '.')
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
            'title' => 'Examen reporté',
            'message' => 'L\' examen du ' . $this->examen->old_start_date . ' a été reporté. au ' . $this->examen->start_date . '.',
            'url' => "/dashboard/student/{$this->examen->id}/candidates"
        ];
    }
}
