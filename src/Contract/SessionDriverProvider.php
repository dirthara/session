<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

use Dirthara\Session\Exception\SessionDriverNotFoundException;

interface SessionDriverProvider
{
    public function has(string $name): bool;

    /**
     * @throws SessionDriverNotFoundException
     */
    public function driver(string $name): SessionDriver;
}
