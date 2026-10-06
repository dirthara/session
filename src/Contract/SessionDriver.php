<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

use Dirthara\Session\Config\SessionConfiguration;

interface SessionDriver
{
    public function create(SessionConfiguration $configuration): SessionStore;
}
