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
