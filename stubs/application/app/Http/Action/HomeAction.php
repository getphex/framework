<?php

declare(strict_types=1);

namespace App\Http\Action;

use Phex\Contracts\Routing\Endpoint;
use Phex\Contracts\Routing\UrlGenerator;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final readonly class HomeAction implements Endpoint
{
    public function __construct(private UrlGenerator $urls) {}

    public function handle(Request $request, Parameters $parameters): Response
    {
        return JsonResponse::create(['hello' => 'world'], headers: new Headers([
            'Link' => ['<'.$this->urls->generate('search').'>; rel="search"'],
        ]));
    }
}
