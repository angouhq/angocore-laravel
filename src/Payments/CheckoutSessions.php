<?php

declare(strict_types=1);

namespace Angou\Angocore\Payments;

use Angou\Angocore\Client;

class CheckoutSessions
{
    public function __construct(private readonly Client $client) {}

    /**
     * Create a hosted checkout session in `subscription` mode by default.
     *
     * @param  array{
     *   plan_id: string,
     *   success_url: string,
     *   cancel_url: string,
     *   provider_account_id?: string,
     *   merchant_id?: string,
     *   customer_id?: string,
     *   customer_email?: string,
     *   customer_name?: string,
     *   mode?: 'subscription'|'payment'|'setup',
     *   trial_days?: int,
     *   metadata?: array<string, mixed>
     * }  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->client->post('checkout-sessions', $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $publicId): array
    {
        return $this->client->get("checkout-sessions/{$publicId}");
    }
}
