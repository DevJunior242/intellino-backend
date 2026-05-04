<?php

namespace App\Services;

use GuzzleHttp\Client;
use Brevo\Client\Configuration;
use Brevo\Client\Model\SendSmtpEmail;
use Brevo\Client\Api\TransactionalEmailsApi;



class BrevoMailService
{
    protected $apiInstance;

    public function __construct()
    {
        $config = Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', env('BREVO_API_KEY'));

        $this->apiInstance = new TransactionalEmailsApi(
            new Client(),
            $config
        );
    }

    public function sendMail($to, $subject, $htmlContent)
    {
        $email = new SendSmtpEmail([
            'subject' => $subject,
            'htmlContent' => $htmlContent,
            'sender' => [
                'name' => env('MAIL_FROM_NAME'),
                'email' => env('MAIL_FROM_ADDRESS'),
            ],
            'to' => [
                ['email' => $to]
            ],
        ]);

        return $this->apiInstance->sendTransacEmail($email);
    }
}
