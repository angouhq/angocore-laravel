<?php

declare(strict_types=1);

namespace Angou\Angocore\Exceptions;

/**
 * AngoCore was still running the first request with this Idempotency-Key when
 * the SDK stopped waiting (ANGOCORE_IN_PROGRESS_WAIT). The operation may still
 * succeed: retry later with the same key to get its result, never with a new
 * key.
 */
class AngocoreIdempotencyInProgressException extends AngocoreException
{
}
