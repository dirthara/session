<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

interface SessionDriverRegistry extends SessionDriverProvider
{
    public function register(string $name, SessionDriver $driver): void;
}
