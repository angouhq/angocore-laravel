<?php

declare(strict_types=1);

namespace Angou\Angocore\Exceptions;

/**
 * 422 idempotency_key_reused: the Idempotency-Key was already used for a
 * request with another method, path or body. It is another operation, so it
 * needs a new key.
 */
class AngocoreIdempotencyKeyReusedException extends AngocoreException
{
}
