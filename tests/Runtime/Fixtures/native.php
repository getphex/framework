<?php

declare(strict_types=1);

use Phex\Runtime\Elephc\NativeOutput;
use Phex\Runtime\Elephc\NativeRequestReader;
use Phex\Tests\Runtime\Fixtures\HttpFixture;

require_once __DIR__.'/../../../examples/compiled-container/http-bootstrap.php';
require_once __DIR__.'/../../../src/Runtime/Elephc/NativeRequestReader.php';
require_once __DIR__.'/../../../src/Runtime/Elephc/NativeOutput.php';
require_once __DIR__.'/HttpFixture.php';

HttpFixture::serve(new NativeRequestReader(), new NativeOutput());
