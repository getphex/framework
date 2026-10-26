<?php

declare(strict_types=1);

use Phex\Runtime\Fpm\GlobalsRequestReader;
use Phex\Runtime\Fpm\SapiOutput;
use Phex\Tests\Runtime\Fixtures\HttpFixture;

require_once __DIR__.'/../../../examples/compiled-container/http-bootstrap.php';
require_once __DIR__.'/../../../src/Runtime/Fpm/GlobalsRequestReader.php';
require_once __DIR__.'/../../../src/Runtime/Fpm/SapiOutput.php';
require_once __DIR__.'/HttpFixture.php';

HttpFixture::serve(new GlobalsRequestReader(), new SapiOutput());
