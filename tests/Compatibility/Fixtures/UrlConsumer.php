<?php

/**
 * Exposes an injected URL generator so tests can verify reserved dependency identity and
 * lifetime.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Routing\UrlGenerator;

final readonly class UrlConsumer
{
    public function __construct(public UrlGenerator $urls) {}
}
