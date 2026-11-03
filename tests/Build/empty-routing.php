<?php

/**
 * Builds an empty compiled route table to test malformed-path handling without route
 * matches.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use Phex\Build\Compiler\BuildConfig;
use Phex\Container\Definition\Container;

return new BuildConfig(new Container([]), 'Phex\\Tests\\Generated\\EmptyRoutingContainer', __DIR__.'/../../var/generated/tests/EmptyRoutingContainer.php');
