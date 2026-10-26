<?php

declare(strict_types=1);

use Phex\Build\Compiler\BuildConfig;
use Phex\Container\Definition\Container;

return new BuildConfig(new Container([]), 'Phex\\Tests\\Generated\\EmptyRoutingContainer', __DIR__.'/../../var/generated/tests/EmptyRoutingContainer.php');
