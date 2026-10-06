<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

use Dirthara\Session\ValueObject\SessionId;

interface Session
{
    public SessionId $id { get; }

    public function has(string $key): bool;

    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value): void;

    public function remove(string $key): void;

    public function clear(): void;

    public function regenerate(): void;

    public function invalidate(): void;
}
