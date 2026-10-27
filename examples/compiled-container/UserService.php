<?php

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

final readonly class UserService
{
    public function __construct(public UserRepository $repository, public RequestSession $session) {}

    public function name(int $id): string
    {
        return $this->repository->name($id);
    }

    public function create(CreateUserInput $input): CreatedUser
    {
        return $this->repository->create($input->name);
    }
}
