<?php

declare(strict_types=1);

namespace Dirthara\Session\Driver;

use Dirthara\Session\Contract\SessionDriver;
use Dirthara\Session\Exception\SessionDriverNotFoundException;
use Dirthara\Session\Exception\DuplicateSessionDriverException;
use Dirthara\Session\Contract\SessionDriverRegistry as SessionDriverRegistryContract;

use function array_key_exists;

final class SessionDriverRegistry implements SessionDriverRegistryContract
{
    /** @var array<string, SessionDriver> */
    private array $drivers = [];

    /**
     * @throws DuplicateSessionDriverException
     */
    public function register(string $name, SessionDriver $driver): void
    {
        if (array_key_exists($name, $this->drivers)) {
            throw DuplicateSessionDriverException::alreadyRegistered($name);
        }

        $this->drivers[$name] = $driver;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->drivers);
    }

    /**
     * @throws SessionDriverNotFoundException
     */
    public function driver(string $name): SessionDriver
    {
        return $this->drivers[$name] ?? throw SessionDriverNotFoundException::for($name);
    }
}
