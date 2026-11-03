<?php

/**
 * Carries immutable application settings shared by the skeleton's handlers and input
 * decoders.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace App\Config;

final readonly class ApplicationSettings
{
    public function __construct(
        public string $greeting,
        public int $pageSize,
        public bool $uppercaseGreeting,
    ) {}
}
