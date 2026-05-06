<?php

declare(strict_types=1);

namespace Angou\Angocore\Exceptions;

class AngocoreRateLimitException extends AngocoreException
{
    public ?int $retryAfter = null;
}
