<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Services\BrevoService;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class SendArbitreAccessCodesNotif extends Notification
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
    public function via($notifiable)
    {
        return [
            'database',
            \App\Notifications\Channels\BrevoChannel::class
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toBrevo($notifiable)
    {
        $email = $notifiable->user?->email;

        if (!$email) {
            return;
        }

        app(BrevoService::class)->send(
            $email,
            $notifiable->user?->fullname  ?? 'Utilisateur',
            'Votre code d\'accès',
            "<h1>Votre code : {$notifiable->code_acces}</h1>"
        );
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
