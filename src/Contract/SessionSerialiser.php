<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

use Dirthara\Session\Exception\SessionSerialisationException;

interface SessionSerialiser
{
    /**
     * @param array<array-key, mixed> $values
     *
     * @throws SessionSerialisationException
     */
    public function serialise(array $values): string;

    /**
     * @return array<array-key, mixed>
     *
     * @throws SessionSerialisationException
     */
    public function deserialise(string $payload): array;
}
