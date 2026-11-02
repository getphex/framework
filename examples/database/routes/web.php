<?php

declare(strict_types=1);

use Phex\Container\Definition\EndpointRoute;
use Phex\Examples\Database\UserEndpoint;
use Phex\Examples\Database\UsersEndpoint;

return [
    new EndpointRoute('GET', '/users', UsersEndpoint::class, name: 'users.index'),
    new EndpointRoute('POST', '/users', UsersEndpoint::class, name: 'users.create'),
    new EndpointRoute('GET', '/users/{id}', UserEndpoint::class, name: 'users.show'),
    new EndpointRoute('PUT', '/users/{id}', UserEndpoint::class, name: 'users.update'),
    new EndpointRoute('DELETE', '/users/{id}', UserEndpoint::class, name: 'users.delete'),
];
