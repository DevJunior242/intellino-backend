<?php

namespace App\Notifications\Channels;

use App\Services\BrevoService;

class BrevoChannel
{
    public function send($notifiable, $notification)
    {
        if (!method_exists($notification, 'toBrevo')) {
            return;
        }

        return $notification->toBrevo($notifiable);
    }
}
