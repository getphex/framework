<?php

declare(strict_types=1);

require_once __DIR__.'/../json-api/bootstrap.php';
require_once __DIR__.'/../../src/Contracts/Kernel/ScopeResource.php';
require_once __DIR__.'/../../src/Contracts/Kernel/HandlerFactory.php';
require_once __DIR__.'/../../src/Kernel/Scope/CleanupFailed.php';
require_once __DIR__.'/../../src/Kernel/Scope/CleanupErrors.php';
require_once __DIR__.'/../../src/Kernel/Scope/ResourceScope.php';
require_once __DIR__.'/../../src/Kernel/Scope/ScopeContext.php';
require_once __DIR__.'/../../src/Kernel/Scope/RequestScope.php';
require_once __DIR__.'/../../src/Kernel/Application.php';
require_once __DIR__.'/RequestTrace.php';
require_once __DIR__.'/ScopeMiddleware.php';
require_once __DIR__.'/LifecycleFactory.php';
