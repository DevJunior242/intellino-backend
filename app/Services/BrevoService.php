<?php

namespace App\Services;

use Brevo\Brevo;
use Illuminate\Support\Facades\Log;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;

class BrevoService
{
    protected Brevo $client;

    public function __construct()
    {
        $this->client = new Brevo(
            apiKey: config('services.brevo.key'),
        );
    }
    public function send(string $toEmail, string $toName, string $subject, string $htmlContent)
    {
        try {
            return $this->client->transactionalEmails->sendTransacEmail(
                new SendTransacEmailRequest([
                    'htmlContent' => $htmlContent,
                    'sender' => new SendTransacEmailRequestSender([
                        'email' => config('mail.from.address'),
                        'name' => config('mail.from.name'),
                    ]),
                    'subject' => $subject,
                    'to' => [
                        new SendTransacEmailRequestToItem([
                            'email' => $toEmail,
                            'name' => $toName,
                        ]),
                    ],
                ])
            );
        } catch (\Throwable $e) {
            Log::error('Brevo error', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
