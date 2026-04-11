<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ArbitrePinNotification extends Notification
{
    use Queueable;
    protected $pin;
    /**
     * Create a new notification instance.
     */
    public function __construct($pin)
    {
        $this->pin = $pin;
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
        return (new MailMessage)
            ->from('contact@ton-saas.com', 'Intellino')
            ->subject('Votre code d\'accès Arbitre')
            ->greeting('Bonjour ' . $notifiable->fullname)
            ->line('La séance de compétition est maintenant ouverte.')
            ->line('Voici votre code PIN personnel pour vous connecter aux tablettes de notation :')
            ->line('**CODE PIN : ' . $this->pin . '**')->line('Ce code est confidentiel, ne le partagez pas.')
            ->action('Accéder à la plateforme', url('/'))
            ->line('Bonne compétition !');
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
