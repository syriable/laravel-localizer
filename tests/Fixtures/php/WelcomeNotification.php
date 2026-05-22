<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

final class WelcomeNotification extends Notification
{
    public function subject(): string
    {
        return __('Welcome to our application');
    }

    public function greeting(): string
    {
        return trans('greetings.formal');
    }

    public function body(int $count): string
    {
        return trans_choice('emails.unread_count', $count);
    }

    public function fallback(): string
    {
        return Lang::get('errors.fallback');
    }

    public function dynamic(string $key): string
    {
        // Intentionally not extracted — dynamic.
        return __($key);
    }
}
