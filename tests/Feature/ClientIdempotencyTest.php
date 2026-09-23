<?php

use Angou\Angocore\Ai\Ai;
use Angou\Angocore\Client;
use Angou\Angocore\Exceptions\AngocoreAuthException;
use Angou\Angocore\Exceptions\AngocoreException;
use Angou\Angocore\Exceptions\AngocoreIdempotencyInProgressException;
use Angou\Angocore\Exceptions\AngocoreIdempotencyKeyReusedException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

beforeEach(function () {
    Sleep::fake();
});

function inProgressResponse(): PromiseInterface
{
    return Http::response(['error' => [
        'type' => 'request_error',
        'code' => 'idempotency_key_in_use',
        'message' => 'A request with this Idempotency-Key is still being processed. Retry with the same key to get its result.',
    ]], 409);
}

/**
 * Fakes AngoCore with `$responses` in order (the last one repeats) and returns
 * the list where the Idempotency-Key of every attempt lands, including
 * attempts that fail to connect.
 */
function fakeAngocore(array $responses): ArrayObject
{
    $keys = new ArrayObject;

    Http::fake(function (Request $request) use ($keys, $responses) {
        $keys->append($request->header('Idempotency-Key')[0] ?? null);

        return $responses[min(count($keys), count($responses)) - 1];
    });

    return $keys;
}

function thrownBy(Closure $call): ?Throwable
{
    try {
        $call();
    } catch (Throwable $e) {
        return $e;
    }

    return null;
}

it('waits and resends with the same key until AngoCore replays the original result', function () {
    $keys = fakeAngocore([inProgressResponse(), inProgressResponse(), Http::response(['id' => 'pi_1'], 201)]);

    $intent = app(Client::class)->post('payment-intents', ['amount' => 50000], 'order-1001-charge');

    expect($intent)->toBe(['id' => 'pi_1'])
        ->and($keys->getArrayCopy())->toBe(array_fill(0, 3, 'order-1001-charge'));
    Sleep::assertSequence([Sleep::for(500)->milliseconds(), Sleep::for(1000)->milliseconds()]);
});

it('stops waiting at the configured limit with an exception the consumer can tell apart', function () {
    config(['angocore.in_progress_wait' => 3]);
    $keys = fakeAngocore([inProgressResponse()]);

    $exception = thrownBy(fn () => app(Client::class)->post('payment-intents', ['amount' => 50000], 'order-1001-charge'));

    expect($exception)->toBeInstanceOf(AngocoreIdempotencyInProgressException::class)
        ->and($exception->httpStatus)->toBe(409)
        ->and($exception->errorBody['error']['code'])->toBe('idempotency_key_in_use')
        ->and($keys->getArrayCopy())->toBe(array_fill(0, 3, 'order-1001-charge'));
    // 0.5 s + 1 s; the next 2 s would pass the 3 s limit.
    Sleep::assertSequence([Sleep::for(500)->milliseconds(), Sleep::for(1000)->milliseconds()]);
});

it('does not wait at all when the limit is zero', function () {
    config(['angocore.in_progress_wait' => 0]);
    $keys = fakeAngocore([inProgressResponse()]);

    expect(thrownBy(fn () => app(Client::class)->post('plans', ['name' => 'Pro'], 'plan-pro')))
        ->toBeInstanceOf(AngocoreIdempotencyInProgressException::class)
        ->and($keys)->toHaveCount(1);
    Sleep::assertNeverSlept();
});

it('keeps the key across a connection retry and the in-progress wait', function () {
    $keys = fakeAngocore([
        Http::failedConnection('cURL error 28: Operation timed out after 10001 milliseconds'),
        inProgressResponse(),
        Http::response(['id' => 'pi_1'], 201),
    ]);

    expect(app(Client::class)->post('payment-intents', ['amount' => 50000], 'order-1001-charge'))->toBe(['id' => 'pi_1'])
        ->and($keys->getArrayCopy())->toBe(array_fill(0, 3, 'order-1001-charge'));
});

it('does not wait on conflicts that are not about the key', function () {
    $keys = fakeAngocore([Http::response(['error' => [
        'code' => 'conflict',
        'message' => 'Payment intent is already in a terminal state.',
    ]], 409)]);

    $exception = thrownBy(fn () => app(Client::class)->post('payment-intents/pi_1/confirm', [], 'confirm-pi_1'));

    expect($exception::class)->toBe(AngocoreException::class)
        ->and($exception->httpStatus)->toBe(409)
        ->and($keys)->toHaveCount(1);
    Sleep::assertNeverSlept();
});

it('surfaces a key reused for another request as its own exception', function () {
    fakeAngocore([Http::response(['error' => [
        'code' => 'idempotency_key_reused',
        'message' => 'This Idempotency-Key was already used for a different request (method, path or body).',
    ]], 422)]);

    $exception = thrownBy(fn () => app(Client::class)->post('payment-intents', ['amount' => 900], 'order-1001-charge'));

    expect($exception)->toBeInstanceOf(AngocoreIdempotencyKeyReusedException::class)
        ->and($exception->httpStatus)->toBe(422)
        ->and($exception->getMessage())->toContain('already used');
});

it('keeps validation errors as a plain AngocoreException', function () {
    fakeAngocore([Http::response(['error' => [
        'code' => 'validation_error',
        'message' => 'The amount field is required.',
        'errors' => ['amount' => ['The amount field is required.']],
    ]], 422)]);

    $exception = thrownBy(fn () => app(Client::class)->post('payment-intents', [], 'order-1001-charge'));

    expect($exception::class)->toBe(AngocoreException::class)
        ->and($exception->errorBody['error']['errors'])->toHaveKey('amount');
});

it('raises AngocoreAuthException on 401 and 403', function (int $status) {
    fakeAngocore([Http::response(['error' => ['code' => 'invalid_credentials', 'message' => 'Invalid or revoked API key.']], $status)]);

    expect(thrownBy(fn () => app(Client::class)->get('merchants')))->toBeInstanceOf(AngocoreAuthException::class);
})->with([401, 403]);

it('sends an Idempotency-Key on every POST and PATCH, generated when none is given', function (string $method) {
    $keys = fakeAngocore([Http::response(['id' => 'sub_1'])]);
    $client = app(Client::class);

    $client->{$method}('subscriptions/sub_1', ['cancel_at_period_end' => true]);
    $client->{$method}('subscriptions/sub_1', ['cancel_at_period_end' => true]);
    $client->{$method}('subscriptions/sub_1', ['cancel_at_period_end' => true], 'cancel-sub-1');

    [$first, $second, $given] = $keys->getArrayCopy();
    expect(Str::isUuid($first))->toBeTrue()
        ->and($second)->not->toBe($first)
        ->and($given)->toBe('cancel-sub-1');
})->with(['post', 'patch']);

it('sends no Idempotency-Key on reads', function () {
    $keys = fakeAngocore([Http::response(['data' => []])]);

    app(Client::class)->get('merchants');

    expect($keys->getArrayCopy())->toBe([null]);
});

it('waits the same way on the AI client', function () {
    $keys = fakeAngocore([inProgressResponse(), Http::response(['id' => 1, 'content' => 'Hola'])]);

    expect(app(Ai::class)->chat([['role' => 'user', 'content' => 'Hola']], ['idempotency_key' => 'chat-1'])['content'])->toBe('Hola')
        ->and($keys->getArrayCopy())->toBe(['chat-1', 'chat-1']);
});
