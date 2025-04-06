<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordBase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPasswordBase implements ShouldQueue
{
    use Queueable;

    /**
     * Create a notification instance.
     *
     * @param  string  $token
     * @return void
     */
    public function __construct($token)
    {
        parent::__construct($token);
    }

    /**
     * Get the reset URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function resetUrl($notifiable)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:8080');

        $url = parent::resetUrl($notifiable);
        
        // Extract token and email from the default URL
        $urlParts = parse_url($url);
        $path = explode('/', $urlParts['path']);
        $token = end($path);
        
        $params = [];
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $params);
        }
        
        // Build a frontend URL with the token and email
        return $frontendUrl . '/reset-password?' . http_build_query([
            'token' => $token,
            'email' => $params['email'] ?? $notifiable->getEmailForPasswordReset()
        ]);
    }

    /**
     * Build the mail representation of the notification.
     *
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Reset Password Notification')
            ->greeting('Hello!')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $this->resetUrl($notifiable))
            ->line('This password reset link will expire in ' . config('auth.passwords.users.expire') . ' minutes.')
            ->line('If you did not request a password reset, no further action is required.')
            ->salutation('Thanks, ' . config('app.name') . ' Team');
    }
} 