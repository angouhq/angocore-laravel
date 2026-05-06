<?php

declare(strict_types=1);

namespace Angou\Angocore\Payments;

use Angou\Angocore\Client;

class Refunds
{
    public function __construct(private readonly Client $client) {}

    /**
     * @param  array{amount?: int, reason?: string, metadata?: array<string, mixed>}  $payload
     * @return array<string, mixed>
     */
    public function create(string $transactionPublicId, array $payload = [], ?string $idempotencyKey = null): array
    {
        return $this->client->post("transactions/{$transactionPublicId}/refund", $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $publicId): array
    {
        return $this->client->get("refunds/{$publicId}");
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array
    {
        return $this->client->get('refunds', $filters);
    }
}
