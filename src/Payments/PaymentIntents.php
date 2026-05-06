<?php

declare(strict_types=1);

namespace Angou\Angocore\Payments;

use Angou\Angocore\Client;

class PaymentIntents
{
    public function __construct(private readonly Client $client) {}

    /**
     * @param  array{
     *   merchant_id: string,
     *   amount: int,
     *   currency: string,
     *   external_reference?: string,
     *   description?: string,
     *   customer?: array{email?: string, name?: string, phone?: string},
     *   payment_method_types?: array<int, string>,
     *   return_url?: string,
     *   cancel_url?: string,
     *   metadata?: array<string, mixed>,
     *   expires_in?: int,
     *   connect_account_public_id?: string,
     *   application_fee_amount?: int
     * }  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->client->post('payment-intents', $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $publicId): array
    {
        return $this->client->get("payment-intents/{$publicId}");
    }

    /** @return array<string, mixed> */
    public function confirm(string $publicId, array $data = [], ?string $idempotencyKey = null): array
    {
        return $this->client->post("payment-intents/{$publicId}/confirm", $data, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function cancel(string $publicId, ?string $idempotencyKey = null): array
    {
        return $this->client->post("payment-intents/{$publicId}/cancel", [], $idempotencyKey);
    }
}
