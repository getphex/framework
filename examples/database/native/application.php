<?php

/**
 * Registers the SQLite application for retained native worker lifetime with fresh request
 * sessions.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use Elephc\Web\Worker;
use Phex\Examples\Database\Generated\ApplicationContainer;
use Phex\Runtime\Elephc\NativeApplication;

require_once __DIR__.'/../../../src/Database/bootstrap.php';
require_once __DIR__.'/../var/generated/native-application-bootstrap.php';

Worker::register(new NativeApplication(new ApplicationContainer()));
