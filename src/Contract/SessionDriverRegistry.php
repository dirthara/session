<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

use Dirthara\Session\Exception\DuplicateSessionDriverException;

interface SessionDriverRegistry extends SessionDriverProvider
{
    /**
     * @throws DuplicateSessionDriverException
     */
    public function register(string $name, SessionDriver $driver): void;
}
