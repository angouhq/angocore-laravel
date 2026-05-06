<?php

declare(strict_types=1);

namespace Angou\Angocore\Payments;

use Angou\Angocore\Client;

class ConnectAccounts
{
    public function __construct(private readonly Client $client) {}

    /**
     * @param  array{
     *   merchant_public_id: string,
     *   country: string,
     *   account_type?: string,
     *   business_email?: string,
     *   metadata?: array<string, mixed>
     * }  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->client->post('connect/accounts', $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $publicId): array
    {
        return $this->client->get("connect/accounts/{$publicId}");
    }

    /**
     * @return array{url: string, expires_at: int}
     */
    public function createOnboardingLink(string $publicId, string $returnUrl, string $refreshUrl): array
    {
        $response = $this->client->post("connect/accounts/{$publicId}/onboarding-link", [
            'return_url' => $returnUrl,
            'refresh_url' => $refreshUrl,
        ]);

        return [
            'url' => (string) ($response['url'] ?? ''),
            'expires_at' => (int) ($response['expires_at'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    public function refresh(string $publicId): array
    {
        return $this->client->post("connect/accounts/{$publicId}/refresh", []);
    }
}
