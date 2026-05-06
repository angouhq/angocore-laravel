<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Base URL del servicio AngoCore
    |--------------------------------------------------------------------------
    | En dev local: http://angocore-api:8000 (dentro de docker compose) o
    | http://host.docker.internal:8000 si el cliente vive en otro stack.
    | En producción: https://core.angou.com.mx
    */
    'base_url' => env('ANGOCORE_BASE_URL', 'http://localhost:8000'),

    /*
    |--------------------------------------------------------------------------
    | Credenciales del ClientApplication
    |--------------------------------------------------------------------------
    | API key emitida desde el panel admin de AngoCore (panel de la app).
    | Una sola key cubre payments + mail según los scopes asignados.
    */
    'api_key' => env('ANGOCORE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    | Debe coincidir con el environment de la API key. AngoCore rechaza
    | requests cuyo header X-AngoCore-Environment no matchee la key.
    */
    'environment' => env('ANGOCORE_ENV', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Webhook secret
    |--------------------------------------------------------------------------
    | Secret HMAC-SHA256 con el que AngoCore firma los webhooks que envía a
    | esta aplicación. Configurado al registrar el ClientApplication.
    */
    'webhook_secret' => env('ANGOCORE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('ANGOCORE_TIMEOUT', 10),
    'retry_times' => (int) env('ANGOCORE_RETRY_TIMES', 2),
    'retry_delay_ms' => (int) env('ANGOCORE_RETRY_DELAY_MS', 200),
];
