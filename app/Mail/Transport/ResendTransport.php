<?php

namespace App\Mail\Transport;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;
use Illuminate\Support\Facades\Http;

class ResendTransport extends AbstractTransport
{
    /**
     * The Resend API key.
     *
     * @var string
     */
    protected $key;

    /**
     * Create a new Resend transport instance.
     *
     * @param  string  $key
     * @return void
     */
    public function __construct(string $key)
    {
        parent::__construct();

        $this->key = $key;
    }

    /**
     * {@inheritDoc}
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->key,
            'Content-Type' => 'application/json',
        ])->post('https://api.resend.com/emails', [
            'from' => $email->getFrom()[0]->toString(),
            'to' => array_map(function ($address) {
                return $address->toString();
            }, $email->getTo()),
            'cc' => array_map(function ($address) {
                return $address->toString();
            }, $email->getCc()),
            'bcc' => array_map(function ($address) {
                return $address->toString();
            }, $email->getBcc()),
            'subject' => $email->getSubject(),
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'reply_to' => $email->getReplyTo() ? $email->getReplyTo()[0]->toString() : null,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Resend API error: ' . $response->body());
        }
    }

    /**
     * Get the string representation of the transport.
     *
     * @return string
     */
    public function __toString(): string
    {
        return 'resend';
    }
} 