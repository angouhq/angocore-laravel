<?php

declare(strict_types=1);

namespace Angou\Angocore\Exceptions;

use RuntimeException;

class AngocoreException extends RuntimeException
{
    /** @var array<string, mixed> */
    public array $errorBody = [];

    public ?int $httpStatus = null;

    /** @param array<string, mixed> $errorBody */
    public static function fromResponse(int $status, array $errorBody, string $fallback): self
    {
        $message = (string) ($errorBody['error']['message'] ?? $errorBody['message'] ?? $fallback);
        $exception = new self($message, $status);
        $exception->httpStatus = $status;
        $exception->errorBody = $errorBody;

        return $exception;
    }
}
