<?php

declare(strict_types=1);

namespace Dirthara\Session;

use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\Contract\SessionIdGenerator;
use Dirthara\Session\Contract\Session as SessionContract;

use function array_key_exists;

final class Session implements SessionContract
{
    /**
     * @var list<SessionId>
     */
    public private(set) array $replacedIds = [];

    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(
        public private(set) SessionId $id,
        private readonly SessionIdGenerator $ids,
        public private(set) array $values = [],
    ) {}

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function clear(): void
    {
        $this->values = [];
    }

    public function regenerate(): void
    {
        $this->replacedIds[] = $this->id;
        $this->id = $this->ids->generate();
    }

    public function invalidate(): void
    {
        $this->clear();
        $this->regenerate();
    }

    public function forgetReplacedIds(): void
    {
        $this->replacedIds = [];
    }
}
