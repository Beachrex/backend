<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one-time link that opens the closed dev/uat website (ADR "Closed dev/uat websites behind an e-mail
 * magic link"). Same look as the password reset e-mail; links to `{FRONTEND_URL}/preview-access/callback`.
 */
class PreviewAccessLinkNotification extends Notification
{
    public function __construct(private readonly string $token, private readonly int $minutes)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            . '/preview-access/callback?' . http_build_query(['token' => $this->token]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->locale ?? app()->getLocale();
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return (new MailMessage)
            ->subject(__('Your link to the Beachrex test website', [], $locale))
            ->view(['emails.preview-access', 'emails.preview-access-text'], [
                'locale' => $locale,
                'url' => $this->url(),
                'host' => (string) parse_url($frontend, PHP_URL_HOST),
                'minutes' => $this->minutes,
                'logoUrl' => $frontend . '/brand/BeachRex_CGP/01_Logo/Horizontal/PNG/beachrex_horizontal_color_1000px.png',
            ]);
    }
}
