<?php

declare(strict_types=1);

namespace Dirthara\Session\Driver\Memory;

use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\ValueObject\StoredSession;

final class MemorySessionStore implements SessionStore
{
    public function read(SessionId $id): ?StoredSession
    {
        // TODO: Implement read() method.
    }

    public function write(SessionId $id, StoredSession $data): void
    {
        // TODO: Implement write() method.
    }

    public function delete(SessionId $id): void
    {
        // TODO: Implement delete() method.
    }
}
