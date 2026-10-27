<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Routing\UrlGenerator;

final readonly class UrlConsumer
{
    public function __construct(public UrlGenerator $urls) {}
}
