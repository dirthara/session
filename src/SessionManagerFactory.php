<?php

declare(strict_types=1);

namespace Dirthara\Session;

use Psr\Clock\ClockInterface;
use Dirthara\Session\Config\SessionConfiguration;
use Dirthara\Session\Contract\SessionIdGenerator;
use Dirthara\Session\Contract\SessionDriverProvider;
use Dirthara\Session\Contract\SessionManager as SessionManagerContract;
use Dirthara\Session\Contract\SessionManagerFactory as SessionManagerFactoryContract;

final readonly class SessionManagerFactory implements SessionManagerFactoryContract
{
    public function __construct(
        private SessionDriverProvider $drivers,
        private SessionIdGenerator $ids,
        private ClockInterface $clock,
    ) {}

    public function create(SessionConfiguration $configuration): SessionManagerContract
    {
        $store = $this->drivers->driver($configuration->driver)->create($configuration);

        return new SessionManager($store, $this->ids, $this->clock, $configuration);
    }
}
