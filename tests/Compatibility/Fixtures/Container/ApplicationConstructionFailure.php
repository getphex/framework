<?php

/**
 * Retains a previously created repository in an application-construction failure for
 * cleanup assertions.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Container;

use Phex\Examples\CompiledContainer\UserRepository;
use RuntimeException;

final class ApplicationConstructionFailure extends RuntimeException
{
    public function __construct(public readonly UserRepository $repository)
    {
        parent::__construct('Application construction failed.');
    }
}
