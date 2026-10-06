<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

interface Session
{
    public function get(string $key, $default = null): mixed;

    // todo other methods
}
