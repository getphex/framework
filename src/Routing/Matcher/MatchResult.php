<?php

declare(strict_types=1);

namespace Phex\Routing\Matcher;

use LogicException;
use Phex\Routing\Definition\Route;
use Phex\Routing\Parameter\Parameters;

final readonly class MatchResult
{
    /** @param list<string> $allowedMethods */
    private function __construct(
        public MatchStatus $status,
        private ?Route $matchedRoute,
        public Parameters $parameters,
        public array $allowedMethods,
    ) {}

    public static function found(Route $route, Parameters $parameters): self
    {
        return new self(MatchStatus::Found, $route, $parameters, []);
    }

    public static function notFound(): self
    {
        return new self(MatchStatus::NotFound, null, new Parameters(), []);
    }

    /** @param list<string> $methods */
    public static function methodNotAllowed(array $methods): self
    {
        return new self(MatchStatus::MethodNotAllowed, null, new Parameters(), $methods);
    }

    public static function badRequest(): self
    {
        return new self(MatchStatus::BadRequest, null, new Parameters(), []);
    }

    public function route(): Route
    {
        $route = $this->matchedRoute;

        if ($route === null) {
            throw new LogicException('This routing result has no matched route.');
        }

        return $route;
    }
}
