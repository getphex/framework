<?php

declare(strict_types=1);

require_once __DIR__.'/../hello-world/bootstrap.php';
require_once __DIR__.'/../../src/Contracts/Routing/Endpoint.php';
require_once __DIR__.'/../../src/Contracts/Routing/EndpointResolver.php';
require_once __DIR__.'/../../src/Contracts/Routing/Matcher.php';
require_once __DIR__.'/../../src/Routing/Parameter/InvalidParameter.php';
require_once __DIR__.'/../../src/Support/IntegerParser.php';
require_once __DIR__.'/../../src/Routing/Parameter/Parameters.php';
require_once __DIR__.'/../../src/Routing/Definition/Segment.php';
require_once __DIR__.'/../../src/Routing/Definition/PathPattern.php';
require_once __DIR__.'/../../src/Routing/Definition/Route.php';
require_once __DIR__.'/../../src/Routing/Matcher/MatchStatus.php';
require_once __DIR__.'/../../src/Routing/Matcher/MatchResult.php';
require_once __DIR__.'/../../src/Routing/Matcher/PathSegments.php';
require_once __DIR__.'/../../src/Routing/Matcher/TableMatcher.php';
require_once __DIR__.'/../../src/Routing/Router.php';
require_once __DIR__.'/HealthEndpoint.php';
require_once __DIR__.'/UserEndpoint.php';
require_once __DIR__.'/JsonApi.php';
