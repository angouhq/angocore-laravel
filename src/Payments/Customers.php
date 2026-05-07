<?php

declare(strict_types=1);

namespace Angou\Angocore\Payments;

use Angou\Angocore\Client;

class Customers
{
    public function __construct(private readonly Client $client) {}

    /**
     * @param  array{
     *   email: string,
     *   merchant_id?: string,
     *   name?: string,
     *   phone?: string,
     *   external_reference?: string,
     *   metadata?: array<string, mixed>
     * }  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->client->post('customers', $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $publicId): array
    {
        return $this->client->get("customers/{$publicId}");
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array
    {
        return $this->client->get('customers', $filters);
    }

    /**
     * @param  array{
     *   email?: string,
     *   name?: ?string,
     *   phone?: ?string,
     *   external_reference?: ?string,
     *   metadata?: array<string, mixed>
     * }  $payload
     * @return array<string, mixed>
     */
    public function update(string $publicId, array $payload): array
    {
        return $this->client->patch("customers/{$publicId}", $payload);
    }
}
