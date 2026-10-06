<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Driver\Memory;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Driver\Memory\MemorySessionStore;

#[CoversClass(MemorySessionStore::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(StoredSession::class)]
final class MemorySessionStoreTest extends TestCase
{
    private const string FIRST_ID = '11111111111111111111111111111111';

    private const string SECOND_ID = '22222222222222222222222222222222';

    private const string THIRD_ID = '33333333333333333333333333333333';

    #[Test]
    public function it_has_nothing_under_an_id_that_was_never_written(): void
    {
        self::assertNull(new MemorySessionStore()->read(new SessionId(self::FIRST_ID)));
    }

    #[Test]
    public function it_reads_the_session_written_under_an_id(): void
    {
        $store = new MemorySessionStore();
        $session = $this->stored('user 42');

        $store->write(new SessionId(self::FIRST_ID), $session);

        self::assertSame($session, $store->read(new SessionId(self::FIRST_ID)));
    }

    #[Test]
    public function it_keeps_the_sessions_under_different_ids_apart(): void
    {
        $store = new MemorySessionStore();
        $first = $this->stored('user 1');
        $second = $this->stored('user 2');

        $store->write(new SessionId(self::FIRST_ID), $first);
        $store->write(new SessionId(self::SECOND_ID), $second);

        self::assertSame($first, $store->read(new SessionId(self::FIRST_ID)));
        self::assertSame($second, $store->read(new SessionId(self::SECOND_ID)));
    }

    #[Test]
    public function it_replaces_the_session_under_an_id(): void
    {
        $store = new MemorySessionStore();
        $replacement = $this->stored('user 2');
        $store->write(new SessionId(self::FIRST_ID), $this->stored('user 1'));

        $store->write(new SessionId(self::FIRST_ID), $replacement);

        self::assertSame($replacement, $store->read(new SessionId(self::FIRST_ID)));
    }

    #[Test]
    public function it_replaces_a_session_only_under_an_id_it_has(): void
    {
        $store = new MemorySessionStore();
        $replacement = $this->stored('user 2');
        $store->write(new SessionId(self::FIRST_ID), $this->stored('user 1'));

        self::assertTrue($store->replace(new SessionId(self::FIRST_ID), $replacement));
        self::assertFalse($store->replace(new SessionId(self::SECOND_ID), $this->stored('user 3')));
        self::assertSame($replacement, $store->read(new SessionId(self::FIRST_ID)));
        self::assertNull($store->read(new SessionId(self::SECOND_ID)));
    }

    #[Test]
    public function it_does_not_replace_a_session_it_deleted(): void
    {
        $store = new MemorySessionStore();
        $store->write(new SessionId(self::FIRST_ID), $this->stored('user 1'));
        $store->delete(new SessionId(self::FIRST_ID));

        self::assertFalse($store->replace(new SessionId(self::FIRST_ID), $this->stored('user 2')));
        self::assertNull($store->read(new SessionId(self::FIRST_ID)));
    }

    #[Test]
    public function it_touches_only_the_expiry_of_a_session_under_an_id_it_has(): void
    {
        $store = new MemorySessionStore();
        $later = new DateTimeImmutable('2026-10-05 16:00:00');
        $store->write(new SessionId(self::FIRST_ID), $this->stored('user 1'));

        self::assertTrue($store->touch(new SessionId(self::FIRST_ID), $later));
        self::assertFalse($store->touch(new SessionId(self::SECOND_ID), $later));
        self::assertSame('user 1', $store->read(new SessionId(self::FIRST_ID))?->payload);
        self::assertEquals(
            new DateTimeImmutable('2026-10-05 10:00:00'),
            $store->read(new SessionId(self::FIRST_ID))?->createdAt,
        );
        self::assertSame($later, $store->read(new SessionId(self::FIRST_ID))?->expiresAt);
        self::assertNull($store->read(new SessionId(self::SECOND_ID)));
    }

    #[Test]
    public function it_keeps_an_expired_session_because_expiry_is_up_to_the_manager(): void
    {
        $store = new MemorySessionStore();
        $expired = new StoredSession(
            'user 42',
            new DateTimeImmutable('2026-10-05 10:00:00'),
            new DateTimeImmutable('2000-01-01 00:00:00'),
        );

        $store->write(new SessionId(self::FIRST_ID), $expired);

        self::assertSame($expired, $store->read(new SessionId(self::FIRST_ID)));
    }

    #[Test]
    public function it_deletes_the_session_under_an_id_and_keeps_the_others(): void
    {
        $store = new MemorySessionStore();
        $kept = $this->stored('user 2');
        $store->write(new SessionId(self::FIRST_ID), $this->stored('user 1'));
        $store->write(new SessionId(self::SECOND_ID), $kept);

        self::assertTrue($store->delete(new SessionId(self::FIRST_ID)));
        self::assertNull($store->read(new SessionId(self::FIRST_ID)));
        self::assertSame($kept, $store->read(new SessionId(self::SECOND_ID)));
    }

    #[Test]
    public function it_reports_that_it_had_no_session_under_an_id_it_deletes(): void
    {
        $store = new MemorySessionStore();
        $store->write(new SessionId(self::FIRST_ID), $this->stored('user 1'));
        $store->delete(new SessionId(self::FIRST_ID));

        self::assertFalse($store->delete(new SessionId(self::FIRST_ID)));
        self::assertFalse($store->delete(new SessionId(self::SECOND_ID)));
    }

    #[Test]
    public function it_prunes_the_sessions_that_expire_by_the_given_moment_and_counts_them(): void
    {
        $store = new MemorySessionStore();
        $store->write(
            new SessionId(self::FIRST_ID),
            new StoredSession(
                'a',
                new DateTimeImmutable('2026-10-05 10:00:00'),
                new DateTimeImmutable('2026-10-05 12:00:00'),
            ),
        );
        $store->write(
            new SessionId(self::SECOND_ID),
            new StoredSession(
                'b',
                new DateTimeImmutable('2026-10-05 10:00:00'),
                new DateTimeImmutable('2026-10-05 13:00:00'),
            ),
        );
        $store->write(
            new SessionId(self::THIRD_ID),
            new StoredSession(
                'c',
                new DateTimeImmutable('2026-10-05 10:00:00'),
                new DateTimeImmutable('2026-10-05 12:00:00.001'),
            ),
        );

        self::assertSame(1, $store->prune(new DateTimeImmutable('2026-10-05 12:00:00')));
        self::assertNull($store->read(new SessionId(self::FIRST_ID)));
        self::assertNotNull($store->read(new SessionId(self::SECOND_ID)));
        self::assertNotNull($store->read(new SessionId(self::THIRD_ID)));
        self::assertSame(2, $store->prune(new DateTimeImmutable('2026-10-05 13:00:00')));
        self::assertSame(0, $store->prune(new DateTimeImmutable('2026-10-05 13:00:00')));
    }

    private function stored(string $payload): StoredSession
    {
        return new StoredSession(
            $payload,
            new DateTimeImmutable('2026-10-05 10:00:00'),
            new DateTimeImmutable('2026-10-05 14:00:00'),
        );
    }
}
