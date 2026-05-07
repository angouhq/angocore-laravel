<?php

declare(strict_types=1);

namespace Angou\Angocore\Payments;

use Angou\Angocore\Client;

class Plans
{
    public function __construct(private readonly Client $client) {}

    /**
     * @param  array{
     *   name: string,
     *   amount: int,
     *   currency: string,
     *   interval: 'day'|'week'|'month'|'year',
     *   description?: string,
     *   interval_count?: int,
     *   trial_days?: int,
     *   merchant_id?: string,
     *   provider_account_id?: string,
     *   metadata?: array<string, mixed>
     * }  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->client->post('plans', $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $publicId): array
    {
        return $this->client->get("plans/{$publicId}");
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array
    {
        return $this->client->get('plans', $filters);
    }

    /**
     * @param  array{
     *   name?: string,
     *   description?: ?string,
     *   status?: 'active'|'archived',
     *   provider_account_id?: ?string,
     *   metadata?: array<string, mixed>
     * }  $payload
     * @return array<string, mixed>
     */
    public function update(string $publicId, array $payload): array
    {
        return $this->client->patch("plans/{$publicId}", $payload);
    }

    /** @return array<string, mixed> */
    public function archive(string $publicId): array
    {
        return $this->client->patch("plans/{$publicId}", ['status' => 'archived']);
    }
}
