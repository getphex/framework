<?php

/**
 * Returns the configured home greeting and a generated link to the named search route.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace App\Http\Action;

use App\Config\ApplicationSettings;
use Phex\Contracts\Routing\Endpoint;
use Phex\Contracts\Routing\UrlGenerator;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final readonly class HomeAction implements Endpoint
{
    public function __construct(private UrlGenerator $urls, private ApplicationSettings $settings) {}

    public function handle(Request $request, Parameters $parameters): Response
    {
        $greeting = $this->settings->greeting;
        if ($this->settings->uppercaseGreeting) {
            $greeting = strtoupper($greeting);
        }

        return JsonResponse::create(['hello' => $greeting], headers: new Headers([
            'Link' => ['<'.$this->urls->generate('search').'>; rel="search"'],
        ]));
    }
}
