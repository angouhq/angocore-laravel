<?php

declare(strict_types=1);

namespace Angou\Angocore\Payments;

use Angou\Angocore\Client;

/**
 * Root facade target for the Pay namespace. Resolved by the ServiceProvider.
 */
class Angopay
{
    public PaymentIntents $paymentIntents;

    public Refunds $refunds;

    public ConnectAccounts $connectAccounts;

    public Webhooks $webhooks;

    public function __construct(Client $client, string $webhookSecret)
    {
        $this->paymentIntents = new PaymentIntents($client);
        $this->refunds = new Refunds($client);
        $this->connectAccounts = new ConnectAccounts($client);
        $this->webhooks = new Webhooks($webhookSecret);
    }

    /** Sugar shortcuts so callers can write Angopay::createPaymentIntent(...). */

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPaymentIntent(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->paymentIntents->create($payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function getPaymentIntent(string $publicId): array
    {
        return $this->paymentIntents->retrieve($publicId);
    }

    /** @return array<string, mixed> */
    public function confirmPaymentIntent(string $publicId, array $data = [], ?string $idempotencyKey = null): array
    {
        return $this->paymentIntents->confirm($publicId, $data, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function cancelPaymentIntent(string $publicId, ?string $idempotencyKey = null): array
    {
        return $this->paymentIntents->cancel($publicId, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function createRefund(string $transactionPublicId, array $payload = [], ?string $idempotencyKey = null): array
    {
        return $this->refunds->create($transactionPublicId, $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function createConnectAccount(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->connectAccounts->create($payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function getConnectAccount(string $publicId): array
    {
        return $this->connectAccounts->retrieve($publicId);
    }

    /** @return array{url: string, expires_at: int} */
    public function createConnectOnboardingLink(string $publicId, string $returnUrl, string $refreshUrl): array
    {
        return $this->connectAccounts->createOnboardingLink($publicId, $returnUrl, $refreshUrl);
    }

    /** @return array<string, mixed> */
    public function handleIncomingWebhook(\Illuminate\Http\Request $request, ?int $toleranceSeconds = null): array
    {
        return $this->webhooks->verify($request, $toleranceSeconds);
    }
}
