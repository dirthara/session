<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Driver\Memory;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Testing\SessionStoreTestCase;
use Dirthara\Session\Driver\Memory\MemorySessionStore;

#[CoversClass(MemorySessionStore::class)]
#[CoversClass(SessionStoreTestCase::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(StoredSession::class)]
final class MemorySessionStoreTest extends SessionStoreTestCase
{
    #[Test]
    public function it_returns_the_session_object_it_was_given(): void
    {
        $store = new MemorySessionStore();
        $session = new StoredSession(
            'user 1',
            new DateTimeImmutable('2026-10-05 10:00:00'),
            new DateTimeImmutable('2026-10-05 14:00:00'),
        );

        $store->write(new SessionId('11111111111111111111111111111111'), $session);

        self::assertSame($session, $store->read(new SessionId('11111111111111111111111111111111')));
    }

    protected function createStore(): SessionStore
    {
        return new MemorySessionStore();
    }
}
