<?php

declare(strict_types=1);

namespace Angou\Angocore\Payments;

use Angou\Angocore\Client;

class Subscriptions
{
    public function __construct(private readonly Client $client) {}

    /**
     * @param  array{
     *   merchant_id: string,
     *   plan_id: string,
     *   customer: array{email: string, external_id?: string},
     *   trial_days?: int,
     *   payment_method_id?: string,
     *   metadata?: array<string, mixed>
     * }  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->client->post('subscriptions', $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $publicId): array
    {
        return $this->client->get("subscriptions/{$publicId}");
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array
    {
        return $this->client->get('subscriptions', $filters);
    }

    /**
     * @param  array{
     *   cancel_at_period_end?: bool,
     *   metadata?: array<string, mixed>
     * }  $payload
     * @return array<string, mixed>
     */
    public function update(string $publicId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->client->patch("subscriptions/{$publicId}", $payload, $idempotencyKey);
    }

    /**
     * Cancel a subscription. By default cancels immediately; pass
     * `at_period_end=true` to leave it active until the period ends.
     *
     * @return array<string, mixed>
     */
    public function cancel(string $publicId, bool $atPeriodEnd = false, ?string $idempotencyKey = null): array
    {
        return $this->client->post(
            "subscriptions/{$publicId}/cancel",
            ['at_period_end' => $atPeriodEnd],
            $idempotencyKey,
        );
    }
}
