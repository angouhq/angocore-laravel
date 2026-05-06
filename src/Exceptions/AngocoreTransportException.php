<?php

declare(strict_types=1);

namespace Angou\Angocore\Exceptions;

use RuntimeException;

/**
 * 5xx or connection failure. Throwing this lets Laravel queue retries pick it up.
 */
class AngocoreTransportException extends RuntimeException
{
}
