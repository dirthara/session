<?php

declare(strict_types=1);

namespace Dirthara\Session;

use Psr\Clock\ClockInterface;
use Dirthara\Session\Contract\SessionSerialiser;
use Dirthara\Session\Config\SessionConfiguration;
use Dirthara\Session\Contract\SessionIdGenerator;
use Dirthara\Session\Contract\SessionDriverProvider;
use Dirthara\Session\Exception\SessionDriverNotFoundException;
use Dirthara\Session\Contract\SessionFactory as SessionFactoryContract;
use Dirthara\Session\Contract\SessionManager as SessionManagerContract;

final readonly class SessionFactory implements SessionFactoryContract
{
    public function __construct(
        private SessionDriverProvider $drivers,
        private SessionIdGenerator $ids,
        private SessionSerialiser $serialiser,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws SessionDriverNotFoundException
     */
    public function create(SessionConfiguration $configuration): SessionManagerContract
    {
        $store = $this->drivers->driver($configuration->driver)->create($configuration);

        return new SessionManager($store, $this->ids, $this->serialiser, $this->clock, $configuration->lifetime);
    }
}
