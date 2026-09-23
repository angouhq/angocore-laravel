<?php

declare(strict_types=1);

namespace Angou\Angocore\Exceptions;

use RuntimeException;

class AngocoreException extends RuntimeException
{
    /** @var array<string, mixed> */
    public array $errorBody = [];

    public ?int $httpStatus = null;

    /**
     * `static`, so each subclass builds itself: with `self`, a 401 surfaced as
     * a plain AngocoreException instead of AngocoreAuthException.
     *
     * @param  array<string, mixed>  $errorBody
     */
    public static function fromResponse(int $status, array $errorBody, string $fallback): static
    {
        $message = (string) ($errorBody['error']['message'] ?? $errorBody['message'] ?? $fallback);
        $exception = new static($message, $status);
        $exception->httpStatus = $status;
        $exception->errorBody = $errorBody;

        return $exception;
    }
}
