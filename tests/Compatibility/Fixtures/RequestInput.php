<?php

/**
 * Supplies deterministic valid or failing request input for shared HTTP host tests.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Runtime\RequestReader;
use Phex\Http\Message\Request;
use Phex\Runtime\Http\InvalidRequest;
use RuntimeException;

final readonly class RequestInput implements RequestReader
{
    public function __construct(private Request $request, private bool $invalid = false, private bool $failure = false) {}

    public function read(): Request
    {
        if ($this->failure) {
            throw new RuntimeException('private failure');
        }
        if ($this->invalid) {
            throw new InvalidRequest('Invalid transport metadata.');
        }

        return $this->request;
    }
}
