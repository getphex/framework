<?php

/**
 * Groups a container definition with its generated class name and output destination.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Compiler;

use Phex\Container\Definition\Container;

final readonly class BuildConfig
{
    public function __construct(public Container $container, public string $className, public string $output) {}
}
