<?php

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
