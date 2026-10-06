<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\Contract\SessionDriver;
use Dirthara\Session\Config\SessionConfiguration;
use Dirthara\Session\Driver\Memory\MemorySessionStore;

final class RecordingSessionDriver implements SessionDriver
{
    /**
     * @var list<SessionConfiguration>
     */
    public private(set) array $configurations = [];

    public function create(SessionConfiguration $configuration): SessionStore
    {
        $this->configurations[] = $configuration;

        return new MemorySessionStore();
    }
}
