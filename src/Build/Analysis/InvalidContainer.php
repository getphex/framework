<?php

/**
 * Reports invalid container definitions so failed analysis cannot replace valid build
 * artifacts.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Analysis;

use InvalidArgumentException;

final class InvalidContainer extends InvalidArgumentException {}
