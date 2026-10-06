<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

use Dirthara\Session\Config\SessionConfiguration;

interface SessionFactory
{
    public function create(SessionConfiguration $configuration): SessionManager;
}
