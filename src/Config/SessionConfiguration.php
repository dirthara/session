<?php

declare(strict_types=1);

namespace Dirthara\Session\Config;

use Dirthara\Session\ValueObject\Duration;
use Dirthara\Session\Exception\InvalidSessionConfigurationException;

use function is_int;
use function is_bool;
use function is_string;
use function array_key_exists;

final readonly class SessionConfiguration
{
    // Browsers cap the lifetime of a cookie at 400 days (RFC 6265bis), so a session cannot usefully outlive one.
    public const int MAXIMUM_LIFETIME_MILLISECONDS = 400 * 86_400_000;

    /**
     * @param array<string, mixed> $options
     *
     * @throws InvalidSessionConfigurationException
     */
    public function __construct(
        public string $driver,
        public Duration $lifetime,
        private array $options = [],
    ) {
        if ($lifetime->milliseconds === 0 || $lifetime->milliseconds > self::MAXIMUM_LIFETIME_MILLISECONDS) {
            throw InvalidSessionConfigurationException::invalidLifetime(
                $driver,
                $lifetime->milliseconds,
                self::MAXIMUM_LIFETIME_MILLISECONDS,
            );
        }
    }

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
