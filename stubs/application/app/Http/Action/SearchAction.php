<?php

/**
 * Decodes typed query options and returns their validated values as JSON.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace App\Http\Action;

use App\Input\SearchDecoder;
use App\Input\SearchInput;
use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Http\Query\QueryString;
use Phex\Routing\Parameter\Parameters;

final readonly class SearchAction implements Endpoint
{
    public function __construct(private SearchDecoder $decoder) {}

    public function handle(Request $request, Parameters $parameters): Response
    {
        $input = QueryString::decode($request, fn (string $query): SearchInput => $this->decoder->decode($query));

        return JsonResponse::create([
            'page' => $input->page,
            'limit' => $input->limit,
            'active' => $input->active,
            'sort' => $input->sort,
        ]);
    }
}
