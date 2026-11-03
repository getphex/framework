<?php

/**
 * Returns injected application settings so tests can observe configuration lifetime and
 * reuse.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Config;

use App\Config\ApplicationSettings;
use Phex\Contracts\Http\Handler;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final readonly class ConfiguredHandler implements Handler
{
    public function __construct(public ApplicationSettings $settings) {}

    public function handle(Request $request): Response
    {
        return JsonResponse::create([
            'greeting' => $this->settings->greeting,
            'size' => $this->settings->pageSize,
            'uppercase' => $this->settings->uppercaseGreeting,
        ]);
    }
}
