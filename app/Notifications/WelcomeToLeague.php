<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeToLeague extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
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
        return (new MailMessage)
            ->line('Bienvenu dans notre league!.')
            ->line('vous pouvez vous connecter à votre compte ici : ' . $baseUrl . '/login')
            ->greeting('Bienvenue ' . $notifiable->fullname);
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
