<?php

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
