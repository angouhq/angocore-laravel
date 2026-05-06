<?php

declare(strict_types=1);

namespace Angou\Angocore\Payments;

use Angou\Angocore\Exceptions\AngocoreException;
use Illuminate\Http\Request;

/**
 * Verify and parse incoming webhooks from AngoCore.
 *
 * Header: X-AngoCore-Signature: t=<unix_ts>,v1=<hex_hmac_sha256>
 * Tolerance: 5 minutes (300s) by default to mitigate replay attacks.
 */
class Webhooks
{
    public const HEADER = 'X-AngoCore-Signature';

    public const DEFAULT_TOLERANCE_SECONDS = 300;

    public function __construct(private readonly string $signingSecret) {}

    /**
     * @return array<string, mixed> Parsed event payload (the JSON body) when valid.
     * @throws AngocoreException When signature is missing/invalid or timestamp is outside tolerance.
     */
    public function verify(Request $request, ?int $toleranceSeconds = null): array
    {
        $tolerance = $toleranceSeconds ?? self::DEFAULT_TOLERANCE_SECONDS;
        $signatureHeader = $request->header(self::HEADER);

        if (! is_string($signatureHeader) || $signatureHeader === '') {
            throw new AngocoreException('Missing X-AngoCore-Signature header.');
        }

        $rawBody = $request->getContent();

        return $this->verifyRaw($rawBody, $signatureHeader, $tolerance);
    }

    /**
     * Lower-level: verify a raw body + signature header pair.
     *
     * @return array<string, mixed>
     */
    public function verifyRaw(string $rawBody, string $signatureHeader, int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS): array
    {
        [$timestamp, $signature] = $this->parseHeader($signatureHeader);

        if (abs(time() - $timestamp) > $toleranceSeconds) {
            throw new AngocoreException("Webhook timestamp outside tolerance ({$toleranceSeconds}s).");
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $this->signingSecret);

        if (! hash_equals($expected, $signature)) {
            throw new AngocoreException('Invalid webhook signature.');
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            throw new AngocoreException('Webhook body is not valid JSON.');
        }

        return $payload;
    }

    /**
     * @return array{0: int, 1: string} [timestamp, signature]
     */
    private function parseHeader(string $header): array
    {
        $timestamp = null;
        $signature = null;

        foreach (explode(',', $header) as $part) {
            $segment = trim($part);
            if (str_starts_with($segment, 't=')) {
                $timestamp = (int) substr($segment, 2);
            } elseif (str_starts_with($segment, 'v1=')) {
                $signature = substr($segment, 3);
            }
        }

        if ($timestamp === null || $timestamp <= 0 || $signature === null || $signature === '') {
            throw new AngocoreException('Malformed X-AngoCore-Signature header.');
        }

        return [$timestamp, $signature];
    }
}
