<?php

declare(strict_types=1);

namespace Dirthara\Session\Config;

use Dirthara\Session\ValueObject\Duration;
use Dirthara\Session\Exception\InvalidSessionConfigurationException;

final readonly class SessionConfiguration
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $driver,
        public Duration $lifetime,
        private array $options = [],
    ) {}

    /**
     * @throws InvalidSessionConfigurationException
     */
    public function string(string $key, ?string $default = null): string
    {
        if (!$this->has($key)) {
            return $default ?? throw InvalidSessionConfigurationException::missingOption($this->driver, $key);
        }

        return is_string($this->options[$key])
            ? $this->options[$key]
            : throw InvalidSessionConfigurationException::invalidOptionType(
                $this->driver,
                $key,
                'string',
                $this->options[$key],
            );
    }

    /**
     * @throws InvalidSessionConfigurationException
     */
    public function int(string $key, ?int $default = null): int
    {
        if (!$this->has($key)) {
            return $default ?? throw InvalidSessionConfigurationException::missingOption($this->driver, $key);
        }

        return is_int($this->options[$key])
            ? $this->options[$key]
            : throw InvalidSessionConfigurationException::invalidOptionType(
                $this->driver,
                $key,
                'int',
                $this->options[$key],
            );
    }

    /**
     * @throws InvalidSessionConfigurationException
     */
    public function bool(string $key, ?bool $default = null): bool
    {
        if (!$this->has($key)) {
            return $default ?? throw InvalidSessionConfigurationException::missingOption($this->driver, $key);
        }

        return is_bool($this->options[$key])
            ? $this->options[$key]
            : throw InvalidSessionConfigurationException::invalidOptionType(
                $this->driver,
                $key,
                'bool',
                $this->options[$key],
            );
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }
}
