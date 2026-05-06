<?php

declare(strict_types=1);

namespace Angou\Angocore\Facades;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Facade;

/**
 * Thin facade pointing to Laravel's Mail manager configured for the "angocore" mailer.
 * Use it as `Angomail::send(new MyMailable(...))` — internally goes through the
 * AngocoreMailTransport.
 *
 * For most use cases just call `Mail::mailer('angocore')->send(...)` directly.
 *
 * @method static \Illuminate\Mail\PendingMail to(mixed $users, ?string $name = null)
 * @method static void send(\Illuminate\Contracts\Mail\Mailable|string|array $view, array $data = [], $callback = null)
 */
class Angomail extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mail.manager';
    }
}
