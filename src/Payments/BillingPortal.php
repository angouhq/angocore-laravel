<?php

declare(strict_types=1);

namespace Angou\Angocore\Payments;

use Angou\Angocore\Client;

class BillingPortal
{
    public function __construct(private readonly Client $client) {}

    /**
     * Create a self-service billing portal session for a customer.
     *
     * @return array<string, mixed>
     */
    public function createSession(
        string $customerPublicId,
        string $returnUrl,
        ?string $providerAccountPublicId = null,
        ?string $idempotencyKey = null,
    ): array {
        $payload = ['return_url' => $returnUrl];
        if ($providerAccountPublicId !== null) {
            $payload['provider_account_id'] = $providerAccountPublicId;
        }

        return $this->client->post(
            "customers/{$customerPublicId}/billing-portal-session",
            $payload,
            $idempotencyKey,
        );
    }
}
