<?php

namespace App\Support;

use Filament\Notifications\Notification;

/**
 * Consequential admin actions stay on screen until dismissed.
 * The default six-second toast made completed actions look as if they had failed.
 */
final class AdminFeedback
{
    public static function success(string $title, ?string $body = null): void
    {
        self::send($title, $body, 'success');
    }

    public static function danger(string $title, ?string $body = null): void
    {
        self::send($title, $body, 'danger');
    }

    public static function warning(string $title, ?string $body = null): void
    {
        self::send($title, $body, 'warning');
    }

    private static function send(string $title, ?string $body, string $status): void
    {
        $notification = Notification::make()->title($title)->persistent()->{$status}();
        if ($body !== null && $body !== '') {
            $notification->body($body);
        }
        $notification->send();
    }
}
