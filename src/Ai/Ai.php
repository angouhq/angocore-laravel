<?php

declare(strict_types=1);

namespace Angou\Angocore\Ai;

use Angou\Angocore\Client;
use Angou\Angocore\Exceptions\AngocoreException;
use JsonException;

/**
 * Chat completions through AngoCore (POST /v1/ai/chat). AngoCore picks the
 * provider (OpenAI first, DeepSeek as fallback), meters usage and cost per
 * application, and hands back a flat array:
 *
 *   ['id', 'provider', 'model', 'content', 'usage' => ['input_tokens', 'output_tokens'],
 *    'cost_usd', 'latency_ms', 'fallback_used']
 */
final class Ai
{
    private const OPTIONS = ['response_format', 'max_tokens', 'temperature', 'metadata'];

    public function __construct(private readonly Client $client) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options  response_format ('text'|'json'),
     *                                        max_tokens, temperature, metadata
     *                                        (product, feature, reference) and
     *                                        idempotency_key.
     * @return array<string, mixed>
     */
    public function chat(array $messages, array $options = []): array
    {
        $body = ['messages' => array_values($messages)];

        foreach (self::OPTIONS as $option) {
            if (array_key_exists($option, $options) && $options[$option] !== null) {
                $body[$option] = $options[$option];
            }
        }

        $idempotencyKey = $options['idempotency_key'] ?? null;

        return $this->client->post('ai/chat', $body, is_string($idempotencyKey) ? $idempotencyKey : null);
    }

    /**
     * Same as chat() with JSON mode on. Returns the decoded object under
     * `data`, plus the raw response under `response`.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array{data: array<string, mixed>, response: array<string, mixed>}
     *
     * @throws AngocoreException when the completion is not valid JSON
     */
    public function json(array $messages, array $options = []): array
    {
        $response = $this->chat($messages, ['response_format' => 'json'] + $options);
        $content = (string) ($response['content'] ?? '');

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AngocoreException('AngoCore AI returned a completion that is not valid JSON.', 0, $e);
        }

        if (! is_array($data)) {
            throw new AngocoreException('AngoCore AI returned a JSON value that is not an object.');
        }

        return ['data' => $data, 'response' => $response];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function text(array $messages, array $options = []): string
    {
        return (string) ($this->chat($messages, $options)['content'] ?? '');
    }
}
