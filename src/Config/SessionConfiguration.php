<?php

declare(strict_types=1);

namespace Dirthara\Session\Config;

final readonly class SessionConfiguration
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $driver,
        private array $options = [],
    ) {}

    // todo methods

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }
}
