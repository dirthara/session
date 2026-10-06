<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Driver\Memory\MemorySessionStore;

final readonly class ExpiringSessionStore implements SessionStore
{
    public function __construct(
        private ClockInterface $clock,
        public MemorySessionStore $inner = new MemorySessionStore(),
    ) {}

    public function read(SessionId $id): ?StoredSession
    {
        $this->expire();

        return $this->inner->read($id);
    }

    public function write(SessionId $id, StoredSession $session): void
    {
        $this->inner->write($id, $session);
    }

    public function replace(SessionId $id, StoredSession $session): bool
    {
        $this->expire();

        return $this->inner->replace($id, $session);
    }

    public function touch(SessionId $id, DateTimeImmutable $expiresAt): bool
    {
        $this->expire();

        return $this->inner->touch($id, $expiresAt);
    }

    public function delete(SessionId $id): bool
    {
        $this->expire();

        return $this->inner->delete($id);
    }

    public function prune(DateTimeImmutable $now): int
    {
        return 0;
    }

    private function expire(): void
    {
        $this->inner->prune($this->clock->now());
    }
}
