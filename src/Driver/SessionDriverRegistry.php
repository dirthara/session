<?php

declare(strict_types=1);

namespace Dirthara\Session\Driver;

use Dirthara\Session\Contract\SessionDriver;
use Dirthara\Session\Contract\SessionDriverRegistry as SessionDriverRegistryContract;

final class SessionDriverRegistry implements SessionDriverRegistryContract
{
    public function has(string $name): bool
    {
        // TODO: Implement has() method.
    }

    public function driver(string $name): SessionDriver
    {
        // TODO: Implement driver() method.
    }

    public function register(string $name, SessionDriver $driver): void
    {
        // TODO: Implement register() method.
    }
}
