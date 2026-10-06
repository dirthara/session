<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

use Dirthara\Session\Config\SessionConfiguration;
use Dirthara\Session\Exception\SessionDriverNotFoundException;

interface SessionFactory
{
    /**
     * @throws SessionDriverNotFoundException
     */
    public function create(SessionConfiguration $configuration): SessionManager;
}
