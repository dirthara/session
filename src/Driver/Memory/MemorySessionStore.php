<?php

declare(strict_types=1);

namespace Dirthara\Session\Driver\Memory;

use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\ValueObject\StoredSession;

final class MemorySessionStore implements SessionStore
{
    /**
     * @var array<string, StoredSession>
     */
    private array $sessions = [];

    public function read(SessionId $id): ?StoredSession
    {
        return $this->sessions[$id->value] ?? null;
    }

    public function write(SessionId $id, StoredSession $session): void
    {
        $this->sessions[$id->value] = $session;
    }

    public function delete(SessionId $id): void
    {
        unset($this->sessions[$id->value]);
    }
}
