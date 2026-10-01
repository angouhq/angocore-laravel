<?php

use Angou\Angocore\Ai\Ai;
use Angou\Angocore\Exceptions\AngocoreAuthException;
use Angou\Angocore\Exceptions\AngocoreRateLimitException;
use Angou\Angocore\Exceptions\AngocoreTransportException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends research instructions, input and supported options and returns the body', function () {
    Http::fake([
        '*/v1/ai/research' => Http::response([
            'content' => 'Research result',
            'json' => null,
            'sources' => [],
            'searches' => 1,
        ]),
    ]);

    $options = [
        'schema' => ['name' => 'quote', 'schema' => ['type' => 'object']],
        'max_output_tokens' => 4000,
        'search' => ['country' => 'MX', 'city' => 'Monterrey'],
        'metadata' => ['product' => 'angogasto'],
        'idempotency_key' => 'research-1',
        'temperature' => 0.2,
        'unexpected' => true,
    ];

    $result = app(Ai::class)->research('Act as a researcher.', 'Find hotels.', $options);

    expect($result)->toBe([
        'content' => 'Research result',
        'json' => null,
        'sources' => [],
        'searches' => 1,
    ]);

    Http::assertSent(function (Request $request) use ($options) {
        return $request->url() === 'https://core.angocore.test/v1/ai/research'
            && $request->header('Idempotency-Key')[0] === 'research-1'
            && $request->data() === [
                'instructions' => 'Act as a researcher.',
                'input' => 'Find hotels.',
                'schema' => $options['schema'],
                'max_output_tokens' => 4000,
                'search' => $options['search'],
                'metadata' => $options['metadata'],
            ];
    });
});

it('maps research authorization, rate limit and provider errors like chat', function (int $status, string $exception) {
    Http::fake([
        '*/v1/ai/research' => Http::response(['error' => ['message' => 'Research failed.']], $status),
    ]);

    expect(fn () => app(Ai::class)->research('Instructions', 'Input'))
        ->toThrow($exception);
})->with([
    [403, AngocoreAuthException::class],
    [429, AngocoreRateLimitException::class],
    [502, AngocoreTransportException::class],
]);
