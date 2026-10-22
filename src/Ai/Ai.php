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

    private const RESEARCH_OPTIONS = ['schema', 'max_output_tokens', 'search', 'metadata'];

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

    /**
     * Reads one or more images and answers `$prompt` as JSON. Each image is
     * an https URL, a `data:image/...;base64` URL, a readable local file path
     * (encoded inline), or `['url' => ..., 'detail' => 'low'|'high'|'auto']`.
     * AngoCore takes at most 4 images of 4 MB each.
     *
     * @param  string|array<int, string|array{url: string, detail?: string}>  $images
     * @param  array<string, mixed>  $options  same as json(), plus `detail`
     *                                        applied to every image and `system`
     *                                        for a leading system message
     * @return array{data: array<string, mixed>, response: array<string, mixed>}
     *
     * @throws AngocoreException when an image cannot be read or the reply is not JSON
     */
    public function vision(string|array $images, string $prompt, array $options = []): array
    {
        $detail = $options['detail'] ?? null;
        $parts = [['type' => 'text', 'text' => $prompt]];

        foreach (is_string($images) ? [$images] : array_values($images) as $image) {
            $parts[] = $this->imagePart($image, is_string($detail) ? $detail : null);
        }

        $messages = [];
        if (is_string($options['system'] ?? null) && $options['system'] !== '') {
            $messages[] = ['role' => 'system', 'content' => $options['system']];
        }
        $messages[] = ['role' => 'user', 'content' => $parts];

        unset($options['detail'], $options['system']);

        return $this->json($messages, $options);
    }

    /**
     * @param  string|array{url: string, detail?: string}  $image
     * @return array{type: string, image_url: array{url: string, detail?: string}}
     */
    private function imagePart(string|array $image, ?string $detail): array
    {
        if (is_array($image)) {
            $detail = is_string($image['detail'] ?? null) ? $image['detail'] : $detail;
            $image = (string) ($image['url'] ?? '');
        }

        $url = str_starts_with($image, 'https://') || str_starts_with($image, 'data:')
            ? $image
            : $this->dataUrlFromFile($image);

        $part = ['type' => 'image_url', 'image_url' => ['url' => $url]];
        if ($detail !== null) {
            $part['image_url']['detail'] = $detail;
        }

        return $part;
    }

    private function dataUrlFromFile(string $path): string
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new AngocoreException("AngoCore AI vision: image must be an https URL, a data URL or a readable file, got [{$path}].");
        }

        $mime = (string) mime_content_type($path);
        if (! str_starts_with($mime, 'image/')) {
            throw new AngocoreException("AngoCore AI vision: [{$path}] is not an image ({$mime}).");
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    /**
     * Web research through AngoCore (POST /v1/ai/research).
     *
     * @param  array<string, mixed>  $options  schema, max_output_tokens, search,
     *                                        metadata and idempotency_key
     * @return array<string, mixed>
     */
    public function research(string $instructions, string $input, array $options = []): array
    {
        $body = [
            'instructions' => $instructions,
            'input' => $input,
        ];

        foreach (self::RESEARCH_OPTIONS as $option) {
            if (array_key_exists($option, $options) && $options[$option] !== null) {
                $body[$option] = $options[$option];
            }
        }

        $idempotencyKey = $options['idempotency_key'] ?? null;

        return $this->client->post('ai/research', $body, is_string($idempotencyKey) ? $idempotencyKey : null);
    }
}
