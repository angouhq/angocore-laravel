<?php

declare(strict_types=1);

namespace Angou\Angocore;

use Angou\Angocore\Exceptions\AngocoreAuthException;
use Angou\Angocore\Exceptions\AngocoreException;
use Angou\Angocore\Exceptions\AngocoreIdempotencyInProgressException;
use Angou\Angocore\Exceptions\AngocoreIdempotencyKeyReusedException;
use Angou\Angocore\Exceptions\AngocoreRateLimitException;
use Angou\Angocore\Exceptions\AngocoreTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Low-level HTTP client. Higher-level resources (PaymentIntents, ConnectAccounts, etc)
 * compose this. Always returns parsed JSON arrays or throws typed exceptions.
 */
class Client
{
    /**
     * Pauses between resends while AngoCore still runs the first request with
     * the same Idempotency-Key; the last one repeats until the wait runs out.
     */
    private const IN_PROGRESS_BACKOFF_MS = [500, 1000, 2000];

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $environment = 'sandbox',
        private readonly int $timeout = 10,
        private readonly int $retryTimes = 2,
        private readonly int $retryDelayMs = 200,
        private readonly int $inProgressWaitSeconds = 15,
    ) {
        if ($this->baseUrl === '') {
            throw new AngocoreException('AngoCore base URL is not configured (ANGOCORE_BASE_URL).');
        }
        if ($this->apiKey === '') {
            throw new AngocoreException('AngoCore API key is not configured (ANGOCORE_API_KEY).');
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $path, array $body, ?string $idempotencyKey = null): array
    {
        return $this->send('post', $path, body: $body, idempotencyKey: $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('get', $path, query: $query);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function patch(string $path, array $body, ?string $idempotencyKey = null): array
    {
        return $this->send('patch', $path, body: $body, idempotencyKey: $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * A 409 idempotency_key_in_use means AngoCore is still running the first
     * request with this key (typically our own retry after a timeout). Resend
     * the same request until AngoCore replays its result or the wait runs out.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $body = [], array $query = [], ?string $idempotencyKey = null): array
    {
        $waitedMs = 0;

        for ($attempt = 0; ; $attempt++) {
            $response = $this->dispatch($method, $path, $body, $query, $idempotencyKey);

            if (! $this->isInProgress($response)) {
                return $this->handleResponse($response);
            }

            $pauseMs = self::IN_PROGRESS_BACKOFF_MS[min($attempt, count(self::IN_PROGRESS_BACKOFF_MS) - 1)];
            if ($waitedMs + $pauseMs > $this->inProgressWaitSeconds * 1000) {
                return $this->handleResponse($response);
            }

            Sleep::for($pauseMs)->milliseconds();
            $waitedMs += $pauseMs;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     */
    private function dispatch(string $method, string $path, array $body, array $query, ?string $idempotencyKey): Response
    {
        $request = $this->request($idempotencyKey);

        try {
            return match ($method) {
                'get' => $request->get($this->url($path), $query),
                'post' => $request->post($this->url($path), $body),
                'patch' => $request->patch($this->url($path), $body),
                default => throw new AngocoreException("Unsupported HTTP method: {$method}"),
            };
        } catch (ConnectionException $e) {
            throw new AngocoreTransportException("AngoCore connection failed: {$e->getMessage()}", 0, $e);
        }
    }

    private function isInProgress(Response $response): bool
    {
        return $response->status() === 409 && $response->json('error.code') === 'idempotency_key_in_use';
    }

    private function request(?string $idempotencyKey): PendingRequest
    {
        $headers = [
            'Authorization' => "Bearer {$this->apiKey}",
            'Accept' => 'application/json',
            'X-AngoCore-Environment' => $this->environment,
        ];
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return Http::withHeaders($headers)
            ->timeout($this->timeout)
            ->retry(
                $this->retryTimes,
                $this->retryDelayMs,
                static fn ($exception) => $exception instanceof ConnectionException,
                throw: false,
            )
            ->acceptJson();
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/v1/'.ltrim($path, '/');
    }

    /** @return array<string, mixed> */
    private function handleResponse(Response $response): array
    {
        $status = $response->status();
        $body = $response->json() ?? [];
        if (! is_array($body)) {
            $body = [];
        }

        if ($response->successful()) {
            return $body;
        }

        if ($status === 401 || $status === 403) {
            throw AngocoreAuthException::fromResponse($status, $body, 'Authentication failed.');
        }

        if ($status === 429) {
            $exception = new AngocoreRateLimitException(
                (string) ($body['error']['message'] ?? 'Rate limit exceeded.'),
                $status,
            );
            $retryAfter = $response->header('Retry-After');
            if ($retryAfter !== null && $retryAfter !== '') {
                $exception->retryAfter = (int) $retryAfter;
            }
            $exception->httpStatus = $status;
            $exception->errorBody = $body;

            throw $exception;
        }

        if ($status >= 500) {
            throw new AngocoreTransportException(
                "AngoCore returned {$status}: ".substr((string) $response->body(), 0, 500),
            );
        }

        $fallback = "AngoCore request failed with status {$status}.";

        throw match ($body['error']['code'] ?? null) {
            'idempotency_key_in_use' => AngocoreIdempotencyInProgressException::fromResponse($status, $body, $fallback),
            'idempotency_key_reused' => AngocoreIdempotencyKeyReusedException::fromResponse($status, $body, $fallback),
            default => AngocoreException::fromResponse($status, $body, $fallback),
        };
    }
}
