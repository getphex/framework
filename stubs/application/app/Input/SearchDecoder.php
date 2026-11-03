<?php

/**
 * Maps bounded query parameters into search options using application-configured defaults.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace App\Input;

use App\Config\ApplicationSettings;
use Phex\Contracts\Http\Decoder;
use Phex\Http\Input\InputError;
use Phex\Http\Query\QueryParameters;

/** @implements Decoder<SearchInput> */
final class SearchDecoder implements Decoder
{
    public function __construct(private readonly ApplicationSettings $settings) {}

    public function decode(string $input): SearchInput
    {
        $query = QueryParameters::decode($input);
        foreach ($query->all() as $parameter) {
            if (!in_array($parameter->name, ['page', 'limit', 'active', 'sort'], true)) {
                throw InputError::validation(['query' => 'Unknown query parameter.']);
            }
        }
        $page = $query->integer('page', 1);
        $limit = $query->integer('limit', $this->settings->pageSize);
        $active = $query->boolean('active', false);
        $sort = $query->get('sort', 'asc');
        if ($page < 1) {
            throw InputError::validation(['query.page' => 'Page must be positive.']);
        }
        if ($limit < 1 || $limit > 100) {
            throw InputError::validation(['query.limit' => 'Limit must be between 1 and 100.']);
        }
        if ($sort !== 'asc' && $sort !== 'desc') {
            throw InputError::validation(['query.sort' => 'Expected asc or desc.']);
        }

        return new SearchInput($page, $limit, $active, $sort);
    }
}
