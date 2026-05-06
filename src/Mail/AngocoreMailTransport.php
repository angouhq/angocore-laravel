<?php

declare(strict_types=1);

namespace Angou\Angocore\Mail;

use Angou\Angocore\Exceptions\AngocoreException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

final class AngocoreMailTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $environment = 'sandbox',
        private readonly int $timeout = 10,
    ) {
        if ($baseUrl === '') {
            throw new AngocoreException('AngoCore base URL is not configured (ANGOCORE_BASE_URL).');
        }
        if ($apiKey === '') {
            throw new AngocoreException('AngoCore API key is not configured (ANGOCORE_API_KEY).');
        }

        parent::__construct();
    }

    public function __toString(): string
    {
        $host = parse_url($this->baseUrl, PHP_URL_HOST) ?: 'unknown';

        return sprintf('angocore://%s', $host);
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        if (! $original instanceof Email) {
            throw new AngocoreException('AngocoreMailTransport only handles Symfony Mime Email messages.');
        }

        $headers = $original->getHeaders();
        $template = $headers->get('X-Angocore-Mail-Template')?->getBodyAsString();
        if ($template === null || $template === '') {
            throw new AngocoreException(
                'Missing X-Angocore-Mail-Template header. Mailables sent through angocore must extend Angou\\Angocore\\Mail\\AngomailTemplate.',
            );
        }

        $dataHeader = $headers->get('X-Angocore-Mail-Data')?->getBodyAsString() ?? '';
        $data = $this->decodeData($dataHeader);

        $recipients = $original->getTo();
        if (count($recipients) > 1) {
            throw new AngocoreException('AngoCore Mail accepts a single "to" recipient per request.');
        }

        $primary = $recipients[0] ?? null;
        if ($primary === null) {
            throw new AngocoreException('Email has no "to" recipient.');
        }

        if ($original->getCc() !== [] || $original->getBcc() !== []) {
            throw new AngocoreException('AngoCore Mail does not support CC/BCC recipients.');
        }

        $payload = [
            'template' => $template,
            'to' => array_filter([
                'email' => $primary->getAddress(),
                'name' => $primary->getName() !== '' ? $primary->getName() : null,
            ], static fn ($value): bool => $value !== null),
            'data' => $data,
        ];

        $explicitFrom = $headers->get('X-Angocore-Mail-From-Email')?->getBodyAsString();
        if ($explicitFrom !== null && $explicitFrom !== '') {
            $explicitName = $headers->get('X-Angocore-Mail-From-Name')?->getBodyAsString();
            $payload['from'] = array_filter([
                'email' => $explicitFrom,
                'name' => $explicitName !== null && $explicitName !== '' ? $explicitName : null,
            ], static fn ($value): bool => $value !== null);
        }

        $idempotencyKey = $headers->get('X-Angocore-Idempotency-Key')?->getBodyAsString();

        try {
            $response = Http::withHeaders(array_filter([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
                'X-AngoCore-Environment' => $this->environment,
                'Idempotency-Key' => $idempotencyKey,
            ], static fn ($value): bool => $value !== null && $value !== ''))
                ->timeout($this->timeout)
                ->retry(2, 200, static fn ($exception) => $exception instanceof ConnectionException, throw: false)
                ->post(rtrim($this->baseUrl, '/').'/v1/mail/send', $payload);
        } catch (ConnectionException $e) {
            throw new TransportException('AngoCore Mail connection failed: '.$e->getMessage(), 0, $e);
        }

        $status = $response->status();

        if ($status === 401 || $status === 403) {
            throw new AngocoreException("AngoCore Mail authentication failed (HTTP {$status}).");
        }

        if ($status === 404) {
            throw new AngocoreException(sprintf('AngoCore template "%s" not found.', $template));
        }

        if ($status === 422) {
            $missing = $response->json('missing') ?? [];
            $missingList = is_array($missing) ? implode(', ', $missing) : '';

            throw new AngocoreException(
                sprintf('AngoCore Mail rejected payload (missing variables): %s', $missingList),
            );
        }

        if ($status >= 400 && $status < 500) {
            throw new AngocoreException(
                sprintf('AngoCore Mail rejected request: %d %s', $status, substr((string) $response->body(), 0, 300)),
            );
        }

        if (! $response->successful()) {
            throw new TransportException(sprintf('AngoCore Mail HTTP error: %d', $status));
        }
    }

    /** @return array<string, mixed> */
    private function decodeData(string $encoded): array
    {
        if ($encoded === '') {
            return [];
        }

        $json = base64_decode($encoded, true);
        if ($json === false) {
            throw new AngocoreException('Corrupt X-Angocore-Mail-Data header (invalid base64).');
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AngocoreException('Corrupt X-Angocore-Mail-Data header (invalid JSON): '.$e->getMessage(), 0, $e);
        }

        if (! is_array($decoded)) {
            throw new AngocoreException('X-Angocore-Mail-Data header must decode to an object/array.');
        }

        return $decoded;
    }
}
