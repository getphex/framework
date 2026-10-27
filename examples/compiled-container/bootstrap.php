<?php

declare(strict_types=1);

require_once __DIR__.'/../request-lifecycle/bootstrap.php';
require_once __DIR__.'/../../src/Contracts/Container/ApplicationContainer.php';
require_once __DIR__.'/../../src/Container/Lifetime/ApplicationScope.php';
require_once __DIR__.'/../../src/Container/Routing/EndpointReference.php';
require_once __DIR__.'/../../src/Contracts/Http/Decoder.php';
require_once __DIR__.'/../../src/Http/Json/InputError.php';
require_once __DIR__.'/../../src/Http/Json/JsonObject.php';
require_once __DIR__.'/../../src/Http/Json/JsonBody.php';
require_once __DIR__.'/../../src/Http/Json/JsonResponse.php';
require_once __DIR__.'/../../src/Http/Json/JsonInputMiddleware.php';
require_once __DIR__.'/CreateUserInput.php';
require_once __DIR__.'/CreateUserDecoder.php';
require_once __DIR__.'/CreatedUser.php';
require_once __DIR__.'/UserRepository.php';
require_once __DIR__.'/Connection.php';
require_once __DIR__.'/MemoryUserRepository.php';
require_once __DIR__.'/RequestSession.php';
require_once __DIR__.'/UserService.php';
require_once __DIR__.'/UserEndpoint.php';
require_once __DIR__.'/CreateUserEndpoint.php';
require_once __DIR__.'/SessionMiddleware.php';
require_once __DIR__.'/Factories.php';
require_once __DIR__.'/../../var/generated/example/ApplicationContainer.php';
