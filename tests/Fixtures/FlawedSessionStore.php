<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

use DateTimeImmutable;
use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Driver\Memory\MemorySessionStore;

use function substr;

final readonly class FlawedSessionStore implements SessionStore
{
    public function __construct(
        private string $flaw,
        private MemorySessionStore $inner = new MemorySessionStore(),
    ) {}

    public function read(SessionId $id): ?StoredSession
    {
        $stored = $this->inner->read($id);

        if ($stored === null || $this->flaw !== 'truncates payloads') {
            return $stored;
        }

        return new StoredSession(
            substr($stored->payload, offset: 0, length: 8),
            $stored->createdAt,
            $stored->expiresAt,
        );
    }

    public function write(SessionId $id, StoredSession $session): void
    {
        if ($this->flaw === 'drops seconds') {
            $session = new StoredSession(
                $session->payload,
                $session->createdAt->setTime(hour: 0, minute: 0),
                $session->expiresAt,
            );
        }

        $this->inner->write($id, $session);
    }

    public function replace(SessionId $id, StoredSession $session): bool
    {
        if ($this->flaw === 'replaces what it does not have') {
            $this->inner->write($id, $session);

            return true;
        }

        return $this->inner->replace($id, $session);
    }

    public function touch(SessionId $id, DateTimeImmutable $expiresAt): bool
    {
        if ($this->flaw === 'touch rewrites the creation moment') {
            $stored = $this->inner->read($id);

            if ($stored !== null) {
                $this->inner->write($id, new StoredSession($stored->payload, $expiresAt, $expiresAt));
            }

            return $stored !== null;
        }

        return $this->inner->touch($id, $expiresAt);
    }

    public function delete(SessionId $id): bool
    {
        $deleted = $this->inner->delete($id);

        return $this->flaw === 'delete always succeeds' || $deleted;
    }

    public function prune(DateTimeImmutable $now): int
    {
        return $this->flaw === 'never prunes' ? 0 : $this->inner->prune($now);
    }
}
