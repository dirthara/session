<?php

declare(strict_types=1);

namespace Dirthara\Session\Driver\Memory;

use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\ValueObject\StoredSession;

use function array_key_exists;

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

    public function replace(SessionId $id, StoredSession $session): bool
    {
        if (!array_key_exists($id->value, $this->sessions)) {
            return false;
        }

        $this->sessions[$id->value] = $session;

        return true;
    }

    public function delete(SessionId $id): bool
    {
        if (!array_key_exists($id->value, $this->sessions)) {
            return false;
        }

        unset($this->sessions[$id->value]);

        return true;
    }
}
