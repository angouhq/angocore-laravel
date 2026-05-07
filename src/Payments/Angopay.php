<?php

declare(strict_types=1);

namespace Angou\Angocore\Payments;

use Angou\Angocore\Client;
use Illuminate\Http\Request;

/**
 * Root facade target for the Pay namespace. Resolved by the ServiceProvider.
 */
class Angopay
{
    public PaymentIntents $paymentIntents;

    public Refunds $refunds;

    public ConnectAccounts $connectAccounts;

    public Customers $customers;

    public Subscriptions $subscriptions;

    public CheckoutSessions $checkoutSessions;

    public BillingPortal $billingPortal;

    public Plans $plans;

    public Webhooks $webhooks;

    public function __construct(Client $client, string $webhookSecret)
    {
        $this->paymentIntents = new PaymentIntents($client);
        $this->refunds = new Refunds($client);
        $this->connectAccounts = new ConnectAccounts($client);
        $this->customers = new Customers($client);
        $this->subscriptions = new Subscriptions($client);
        $this->checkoutSessions = new CheckoutSessions($client);
        $this->billingPortal = new BillingPortal($client);
        $this->plans = new Plans($client);
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

    /* Customers */

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCustomer(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->customers->create($payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function getCustomer(string $publicId): array
    {
        return $this->customers->retrieve($publicId);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateCustomer(string $publicId, array $payload): array
    {
        return $this->customers->update($publicId, $payload);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listCustomers(array $filters = []): array
    {
        return $this->customers->list($filters);
    }

    /* Subscriptions */

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createSubscription(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->subscriptions->create($payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function getSubscription(string $publicId): array
    {
        return $this->subscriptions->retrieve($publicId);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateSubscription(string $publicId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->subscriptions->update($publicId, $payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function cancelSubscription(string $publicId, bool $atPeriodEnd = false, ?string $idempotencyKey = null): array
    {
        return $this->subscriptions->cancel($publicId, $atPeriodEnd, $idempotencyKey);
    }

    /* Checkout sessions */

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCheckoutSession(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->checkoutSessions->create($payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function getCheckoutSession(string $publicId): array
    {
        return $this->checkoutSessions->retrieve($publicId);
    }

    /* Billing portal */

    /** @return array<string, mixed> */
    public function createBillingPortalSession(
        string $customerPublicId,
        string $returnUrl,
        ?string $providerAccountPublicId = null,
        ?string $idempotencyKey = null,
    ): array {
        return $this->billingPortal->createSession(
            $customerPublicId,
            $returnUrl,
            $providerAccountPublicId,
            $idempotencyKey,
        );
    }

    /* Plans */

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPlan(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->plans->create($payload, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function getPlan(string $publicId): array
    {
        return $this->plans->retrieve($publicId);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updatePlan(string $publicId, array $payload): array
    {
        return $this->plans->update($publicId, $payload);
    }

    /** @return array<string, mixed> */
    public function archivePlan(string $publicId): array
    {
        return $this->plans->archive($publicId);
    }

    /* Webhooks */

    /** @return array<string, mixed> */
    public function handleIncomingWebhook(Request $request, ?int $toleranceSeconds = null): array
    {
        return $this->webhooks->verify($request, $toleranceSeconds);
    }
}
