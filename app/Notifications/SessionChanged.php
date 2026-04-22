<?php

namespace App\Notifications;

use App\Models\SessionModel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\HttpFoundation\Session\Session;

class SessionChanged extends Notification implements ShouldQueue
{
    use Queueable;
    public SessionModel $session;

    /**
     * Create a new notification instance.
     */
    public function __construct($session)
    {
        $this->session = $session;
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
    public function toMail($notifiable)
    {
        $clubId = optional($this->session->course)->club_id;

        $url = config('app.frontend_url') . "/dashboard/session/{$this->session->id}/show?club_id={$clubId}";

        return (new MailMessage)
            ->subject('Mise à jour de votre séance')
            ->greeting('Bonjour ' . $notifiable->fullname)
            ->line('La séance de sport du ' . $this->session->session_date . ' a été modifiée.')
            ->action('Voir les détails de la séance', $url)
            ->line('Merci d\'utiliser Intellino !');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable)
    {
        $clubId = optional($this->session->course)->club_id;
        return [
            'title' => 'Séance modifiée',
            'message' => 'Le cours du ' . $this->session->date . ' a été mis à jour.',
            'icon' => 'calendar_month',
            'type' => 'info',
            'url' => "/dashboard/session/{$this->session->id}/show?club_id={$clubId}"
        ];
    }
}
