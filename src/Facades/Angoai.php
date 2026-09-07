<?php

declare(strict_types=1);

namespace Angou\Angocore\Facades;

use Angou\Angocore\Ai\Ai as AiService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array chat(array $messages, array $options = [])
 * @method static array json(array $messages, array $options = [])
 * @method static string text(array $messages, array $options = [])
 *
 * @see AiService
 */
class Angoai extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AiService::class;
    }
}
