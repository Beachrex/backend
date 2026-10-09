<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset e-mail in the visitor's language with a link to the website's reset page
 * (`{FRONTEND_URL}/{locale}/auth/reset-password`), not to an API route.
 */
class ResetPasswordNotification extends Notification
{
    /** The website uses 'si' for Slovenian; the API locale is 'sl'. */
    private const FRONTEND_LOCALES = ['sl' => 'si'];

    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function resetUrl(object $notifiable, string $locale): string
    {
        $frontendLocale = self::FRONTEND_LOCALES[$locale] ?? $locale;

        return rtrim((string) config('app.frontend_url'), '/')
            . "/{$frontendLocale}/auth/reset-password?"
            . http_build_query(['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->locale ?? app()->getLocale();
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject(__('Reset your Beachrex password', [], $locale))
            ->view(['emails.password-reset', 'emails.password-reset-text'], [
                'locale' => $locale,
                'url' => $this->resetUrl($notifiable, $locale),
                'minutes' => $minutes,
                'logoUrl' => rtrim((string) config('app.frontend_url'), '/')
                    . '/brand/BeachRex_CGP/01_Logo/Horizontal/PNG/beachrex_horizontal_color_1000px.png',
            ]);
    }
}
