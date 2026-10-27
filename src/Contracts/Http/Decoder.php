<?php

declare(strict_types=1);

namespace Phex\Contracts\Http;

/** @template-covariant T of object */
interface Decoder
{
    /** @return T */
    public function decode(string $input): object;
}
