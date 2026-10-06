<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

interface SessionSerialiser
{
    /**
     * @param array<array-key, mixed> $values
     */
    public function serialise(array $values): string;

    /**
     * @return array<array-key, mixed>
     */
    public function deserialise(string $payload): array;
}
