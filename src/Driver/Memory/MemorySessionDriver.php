<?php

declare(strict_types=1);

namespace Dirthara\Session\Driver\Memory;

use Dirthara\Session\Contract\SessionId;
use Dirthara\Session\Contract\SessionData;
use Dirthara\Session\Contract\SessionDriver;

final class MemorySessionDriver implements SessionDriver
{
    public function read(SessionId $id): ?SessionData
    {
        // TODO: Implement read() method.
    }

    public function write(SessionId $id, SessionData $data): void
    {
        // TODO: Implement write() method.
    }

    public function delete(SessionId $id): void
    {
        // TODO: Implement delete() method.
    }
}
