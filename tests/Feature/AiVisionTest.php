<?php

use Angou\Angocore\Ai\Ai;
use Angou\Angocore\Exceptions\AngocoreException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const VISION_PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\xdac\xf8\xff\xff?\x00\x05\xfe\x02\xfe\xa7V\xbd\xfa\x00\x00\x00\x00IEND\xaeB`\x82";

function fakeVisionReply(): void
{
    Http::fake(['*/v1/ai/chat' => Http::response([
        'content' => '{"platillos":[{"nombre":"Asada","precio":22}]}',
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
    ])]);
}

it('builds one user message with the prompt and image parts and returns decoded JSON', function () {
    fakeVisionReply();

    $result = app(Ai::class)->vision(
        ['https://cdn.example.com/menu.jpg', ['url' => 'data:image/png;base64,AAAA', 'detail' => 'high']],
        'Lista los platillos',
        ['detail' => 'low', 'metadata' => ['product' => 'antojo'], 'idempotency_key' => 'menu-1', 'max_tokens' => 300],
    );

    expect($result['data'])->toBe(['platillos' => [['nombre' => 'Asada', 'precio' => 22]]])
        ->and($result['response']['provider'])->toBe('openai');

    Http::assertSent(fn (Request $request) => $request->header('Idempotency-Key')[0] === 'menu-1'
        && $request->data() === [
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Lista los platillos'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'https://cdn.example.com/menu.jpg', 'detail' => 'low']],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,AAAA', 'detail' => 'high']],
                ],
            ]],
            'response_format' => 'json',
            'max_tokens' => 300,
            'metadata' => ['product' => 'antojo'],
        ]);
});

it('accepts a single image string and an optional system message', function () {
    fakeVisionReply();

    app(Ai::class)->vision('https://cdn.example.com/menu.jpg', 'Lee el menú', ['system' => 'Responde en español.']);

    Http::assertSent(fn (Request $request) => $request['messages'][0] === ['role' => 'system', 'content' => 'Responde en español.']
        && $request['messages'][1]['content'][1] === ['type' => 'image_url', 'image_url' => ['url' => 'https://cdn.example.com/menu.jpg']]
        && ! array_key_exists('system', $request->data())
        && ! array_key_exists('detail', $request->data()));
});

it('encodes a local image file as an inline data URL', function () {
    fakeVisionReply();
    $path = tempnam(sys_get_temp_dir(), 'menu').'.png';
    file_put_contents($path, VISION_PNG);

    try {
        app(Ai::class)->vision($path, 'Lee el menú');
    } finally {
        unlink($path);
    }

    Http::assertSent(fn (Request $request) => $request['messages'][0]['content'][1]['image_url']['url']
        === 'data:image/png;base64,'.base64_encode(VISION_PNG));
});

it('rejects an image that is neither a URL nor a readable image file', function (string $image, string $message) {
    Http::fake();

    expect(fn () => app(Ai::class)->vision($image, 'Lee el menú'))
        ->toThrow(AngocoreException::class, $message);

    Http::assertNothingSent();
})->with([
    'missing file' => ['/nonexistent/menu.jpg', 'must be an https URL, a data URL or a readable file'],
    'http url' => ['http://cdn.example.com/menu.jpg', 'must be an https URL, a data URL or a readable file'],
    'not an image' => [__FILE__, 'is not an image'],
]);

it('fails like json() when the completion is not valid JSON', function () {
    Http::fake(['*/v1/ai/chat' => Http::response(['content' => 'no json here'])]);

    expect(fn () => app(Ai::class)->vision('https://cdn.example.com/menu.jpg', 'Lee el menú'))
        ->toThrow(AngocoreException::class, 'not valid JSON');
});
