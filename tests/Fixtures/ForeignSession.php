<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

use Dirthara\Session\Contract\Session;
use Dirthara\Session\ValueObject\SessionId;

final class ForeignSession implements Session
{
    public function __construct(
        public private(set) SessionId $id,
    ) {}

    public function has(string $key): bool
    {
        return false;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function put(string $key, mixed $value): void {}

    public function remove(string $key): void {}

    public function clear(): void {}

    public function regenerate(): void {}

    public function invalidate(): void {}
}
