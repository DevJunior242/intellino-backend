<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeNewMember extends Notification
{
    use Queueable;
    public $token;

    /**
     * Create a new notification instance.
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        //$url = "http://localhost:5173/reset-password/" . $this->token . "?email=" . urlencode($notifiable->email) . "&first=true";
        $baseUrl = config('app.frontend_url');
        $url = $baseUrl . "/reset-password/" . $this->token . "?email=" . urlencode($notifiable->email) . "&first=true";
        return (new MailMessage)
            ->line('Bienvenu dans notre club!.')
            ->greeting('Bienvenue ' . $notifiable->fullname)
            ->line('pour accéder à votre espace, vous devez definir votre mot de passe.')
            ->action('Définir votre mot de passe', $url)
            ->line('le lien ne sera valide que 24 heures.')
            ->line('Si vous n\'avez pas demandé cette inscription, ignorez simplement ce mail.');;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
