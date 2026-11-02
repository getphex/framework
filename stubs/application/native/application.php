<?php

declare(strict_types=1);

use App\Generated\ApplicationContainer;
use Elephc\Web\Worker;
use Phex\Runtime\Elephc\NativeApplication;

require_once __DIR__.'/../var/generated/native-application-bootstrap.php';

Worker::register(new NativeApplication(new ApplicationContainer()));
