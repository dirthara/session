<?php

declare(strict_types=1);

namespace Dirthara\Session;

use Dirthara\Session\ValueObject\SessionId;

use function array_key_exists;

final class Session
{
    public SessionId $id {
        get => $this->state->id;
    }

    public function __construct(
        private readonly SessionState $state,
    ) {}

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->state->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->state->values) ? $this->state->values[$key] : $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->state->values[$key] = $value;
        $this->state->changed = true;
    }

    public function remove(string $key): void
    {
        unset($this->state->values[$key]);
        $this->state->changed = true;
    }

    public function clear(): void
    {
        $this->state->values = [];
        $this->state->changed = true;
    }
}
