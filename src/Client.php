<?php

declare(strict_types=1);

namespace Angou\Angocore;

use Angou\Angocore\Exceptions\AngocoreAuthException;
use Angou\Angocore\Exceptions\AngocoreException;
use Angou\Angocore\Exceptions\AngocoreRateLimitException;
use Angou\Angocore\Exceptions\AngocoreTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Low-level HTTP client. Higher-level resources (PaymentIntents, ConnectAccounts, etc)
 * compose this. Always returns parsed JSON arrays or throws typed exceptions.
 */
class Client
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $environment = 'sandbox',
        private readonly int $timeout = 10,
        private readonly int $retryTimes = 2,
        private readonly int $retryDelayMs = 200,
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
        return $this->send('patch', $path, body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $body = [], array $query = [], ?string $idempotencyKey = null): array
    {
        $request = $this->request($idempotencyKey);

        try {
            $response = match ($method) {
                'get' => $request->get($this->url($path), $query),
                'post' => $request->post($this->url($path), $body),
                'patch' => $request->patch($this->url($path), $body),
                default => throw new AngocoreException("Unsupported HTTP method: {$method}"),
            };
        } catch (ConnectionException $e) {
            throw new AngocoreTransportException("AngoCore connection failed: {$e->getMessage()}", 0, $e);
        }

        return $this->handleResponse($response);
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

        throw AngocoreException::fromResponse($status, $body, "AngoCore request failed with status {$status}.");
    }
}
