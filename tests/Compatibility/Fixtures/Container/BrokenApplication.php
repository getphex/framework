<?php

/**
 * Throws during application service construction after a dependent resource has been
 * created.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Container;

use Phex\Examples\CompiledContainer\UserRepository;

final class BrokenApplication
{
    public function __construct(UserRepository $repository)
    {
        throw new ApplicationConstructionFailure($repository);
    }
}
