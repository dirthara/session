<?php

declare(strict_types=1);

namespace Dirthara\Session\Driver\Memory;

use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\Contract\SessionDriver;
use Dirthara\Session\Config\SessionConfiguration;

final readonly class MemorySessionDriver implements SessionDriver
{
    public function create(SessionConfiguration $configuration): SessionStore
    {
        return new MemorySessionStore();
    }
}
