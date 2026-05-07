<?php

declare(strict_types=1);

namespace Angou\Angocore\Facades;

use Angou\Angocore\Payments\Angopay as AngopayService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array createPaymentIntent(array $payload, ?string $idempotencyKey = null)
 * @method static array getPaymentIntent(string $publicId)
 * @method static array confirmPaymentIntent(string $publicId, array $data = [], ?string $idempotencyKey = null)
 * @method static array cancelPaymentIntent(string $publicId, ?string $idempotencyKey = null)
 * @method static array createRefund(string $transactionPublicId, array $payload = [], ?string $idempotencyKey = null)
 * @method static array createConnectAccount(array $payload, ?string $idempotencyKey = null)
 * @method static array getConnectAccount(string $publicId)
 * @method static array createConnectOnboardingLink(string $publicId, string $returnUrl, string $refreshUrl)
 * @method static array createCustomer(array $payload, ?string $idempotencyKey = null)
 * @method static array getCustomer(string $publicId)
 * @method static array updateCustomer(string $publicId, array $payload)
 * @method static array listCustomers(array $filters = [])
 * @method static array createSubscription(array $payload, ?string $idempotencyKey = null)
 * @method static array getSubscription(string $publicId)
 * @method static array updateSubscription(string $publicId, array $payload, ?string $idempotencyKey = null)
 * @method static array cancelSubscription(string $publicId, bool $atPeriodEnd = false, ?string $idempotencyKey = null)
 * @method static array createCheckoutSession(array $payload, ?string $idempotencyKey = null)
 * @method static array getCheckoutSession(string $publicId)
 * @method static array createBillingPortalSession(string $customerPublicId, string $returnUrl, ?string $providerAccountPublicId = null, ?string $idempotencyKey = null)
 * @method static array createPlan(array $payload, ?string $idempotencyKey = null)
 * @method static array getPlan(string $publicId)
 * @method static array updatePlan(string $publicId, array $payload)
 * @method static array archivePlan(string $publicId)
 * @method static array handleIncomingWebhook(\Illuminate\Http\Request $request, ?int $toleranceSeconds = null)
 *
 * @see AngopayService
 */
class Angopay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AngopayService::class;
    }
}
