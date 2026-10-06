<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

interface SessionDriverProvider
{
    public function has(string $name): bool;

    public function driver(string $name): SessionDriver;
}
