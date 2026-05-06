<?php

namespace App\Notifications;

use App\Models\Examen;
use Illuminate\Bus\Queueable;
use App\Services\BrevoService;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ExamenCreated extends Notification implements ShouldQueue
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
    // public function toMail(object $notifiable): MailMessage
    // {
    //     $clubId = optional($this->examen)->club_id;
    //     $url = config('app.frontend_url') . "/dashboard/examen/{$this->examen->id}/show?club_id={$clubId}";
    //     return (new MailMessage)
    //         ->line('Salut ' . $notifiable->fullname)
    //         ->line('vous avez un nouvel examen du ' . $this->examen->date . '.')
    //         ->action('Voir les détails de l\'examen', $url)
    //         ->line('Merci d\'utiliser Intellino !');
    // }

    public function toBrevo($notifiable)
    {
        $email = $notifiable->email
            ?? $notifiable->user?->email
            ?? null;

        if (!$email) {
            return;
        }

        $clubId = optional($this->examen)->organisateur_id;
        // /dashboard/examen/019dfbb1-956c-70bf-8ac8-f1e7f14b0dc1/show
        $url = config('app.frontend_url') . "/dashboard/examen/{$this->examen->id}/show";

        $html = "
        <h1>Salut {$notifiable->user?->fullname},</h1>
        <p>Vous avez un nouvel examen du <strong>{$this->examen->start_date}</strong>.</p>
        <a href='{$url}' style='
            background-color: #4F46E5;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            display: inline-block;
            margin-top: 16px;
        '>
            Voir les détails de l'examen
        </a>
        <p style='margin-top: 24px;'>Merci d'utiliser Intellino !</p>
    ";

        app(BrevoService::class)->send(
            $email,
            $notifiable->user?->fullname ?? 'Utilisateur',
            'Nouvel examen',
            $html
        );
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
            'title' => 'Nouvel examen',
            'message' => 'vous avez un nouvel examen du ' . $this->examen->start_date . '.',
            'url' => "/dashboard/student/{$this->examen->id}/candidates"
        ];
    }
}
