<?php

declare(strict_types=1);

namespace Dirthara\Session\Testing;

use DateTimeZone;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\ValueObject\StoredSession;

abstract class SessionStoreTestCase extends TestCase
{
    private const string FIRST_ID = '11111111111111111111111111111111';

    private const string SECOND_ID = '22222222222222222222222222222222';

    private const string THIRD_ID = '33333333333333333333333333333333';

    #[Test]
    public function it_has_nothing_under_an_id_that_was_never_written(): void
    {
        self::assertNull($this->createStore()->read(new SessionId(self::FIRST_ID)));
    }

    #[Test]
    public function it_reads_the_session_written_under_an_id(): void
    {
        $store = $this->createStore();
        $session = $this->stored('user 1');

        $store->write(new SessionId(self::FIRST_ID), $session);

        $this->assertStored($session, $store->read(new SessionId(self::FIRST_ID)));
    }

    #[Test]
    public function it_keeps_every_byte_of_a_payload(): void
    {
        $store = $this->createStore();
        $session = $this->stored("a:1:{s:4:\"name\";s:6:\"\0\xFF\xE9\n\r\t\";}");

        $store->write(new SessionId(self::FIRST_ID), $session);

        $this->assertStored($session, $store->read(new SessionId(self::FIRST_ID)));
    }

    #[Test]
    public function it_keeps_the_sessions_under_different_ids_apart(): void
    {
        $store = $this->createStore();
        $first = $this->stored('user 1');
        $second = $this->stored('user 2');

        $store->write(new SessionId(self::FIRST_ID), $first);
        $store->write(new SessionId(self::SECOND_ID), $second);

        $this->assertStored($first, $store->read(new SessionId(self::FIRST_ID)));
        $this->assertStored($second, $store->read(new SessionId(self::SECOND_ID)));
    }

    #[Test]
    public function it_writes_over_the_session_under_an_id(): void
    {
        $store = $this->createStore();
        $replacement = $this->stored('user 2');
        $store->write(new SessionId(self::FIRST_ID), $this->stored('user 1'));

        $store->write(new SessionId(self::FIRST_ID), $replacement);

        $this->assertStored($replacement, $store->read(new SessionId(self::FIRST_ID)));
    }

    #[Test]
    public function it_replaces_a_session_only_under_an_id_it_has(): void
    {
        $store = $this->createStore();
        $replacement = $this->stored('user 2');
        $store->write(new SessionId(self::FIRST_ID), $this->stored('user 1'));

        self::assertTrue($store->replace(new SessionId(self::FIRST_ID), $replacement));
        self::assertFalse($store->replace(new SessionId(self::SECOND_ID), $this->stored('user 3')));
        $this->assertStored($replacement, $store->read(new SessionId(self::FIRST_ID)));
        self::assertNull($store->read(new SessionId(self::SECOND_ID)));
    }

    #[Test]
    public function it_does_not_replace_a_session_it_deleted(): void
    {
        $store = $this->createStore();
        $store->write(new SessionId(self::FIRST_ID), $this->stored('user 1'));
        $store->delete(new SessionId(self::FIRST_ID));

        self::assertFalse($store->replace(new SessionId(self::FIRST_ID), $this->stored('user 2')));
        self::assertNull($store->read(new SessionId(self::FIRST_ID)));
    }

    #[Test]
    public function it_touches_only_the_expiry_of_a_session_under_an_id_it_has(): void
    {
        $store = $this->createStore();
        $later = $this->moment('2026-10-05 16:00:00.500');
        $store->write(new SessionId(self::FIRST_ID), $this->stored('user 1'));

        self::assertTrue($store->touch(new SessionId(self::FIRST_ID), $later));
        self::assertFalse($store->touch(new SessionId(self::SECOND_ID), $later));
        $this->assertStored(
            new StoredSession('user 1', $this->moment('2026-10-05 10:00:00.125'), $later),
            $store->read(new SessionId(self::FIRST_ID)),
        );
        self::assertNull($store->read(new SessionId(self::SECOND_ID)));
    }

    #[Test]
    public function it_keeps_an_expired_session_because_expiry_is_up_to_the_manager(): void
    {
        $store = $this->createStore();
        $expired = new StoredSession(
            'user 1',
            $this->moment('2000-01-01 00:00:00'),
            $this->moment('2000-01-01 02:00:00'),
        );

        $store->write(new SessionId(self::FIRST_ID), $expired);

        $this->assertStored($expired, $store->read(new SessionId(self::FIRST_ID)));
    }

    #[Test]
    public function it_deletes_the_session_under_an_id_and_keeps_the_others(): void
    {
        $store = $this->createStore();
        $kept = $this->stored('user 2');
        $store->write(new SessionId(self::FIRST_ID), $this->stored('user 1'));
        $store->write(new SessionId(self::SECOND_ID), $kept);

        self::assertTrue($store->delete(new SessionId(self::FIRST_ID)));
        self::assertNull($store->read(new SessionId(self::FIRST_ID)));
        $this->assertStored($kept, $store->read(new SessionId(self::SECOND_ID)));
    }

    #[Test]
    public function it_reports_that_it_had_no_session_under_an_id_it_deletes(): void
    {
        $store = $this->createStore();
        $store->write(new SessionId(self::FIRST_ID), $this->stored('user 1'));
        $store->delete(new SessionId(self::FIRST_ID));

        self::assertFalse($store->delete(new SessionId(self::FIRST_ID)));
        self::assertFalse($store->delete(new SessionId(self::SECOND_ID)));
    }

    #[Test]
    public function it_prunes_the_sessions_that_expire_by_the_given_moment_and_counts_them(): void
    {
        $store = $this->createStore();
        $createdAt = $this->moment('2026-10-05 10:00:00');
        $store->write(
            new SessionId(self::FIRST_ID),
            new StoredSession('a', $createdAt, $this->moment('2026-10-05 12:00:00')),
        );
        $store->write(
            new SessionId(self::SECOND_ID),
            new StoredSession('b', $createdAt, $this->moment('2026-10-05 13:00:00')),
        );
        $store->write(
            new SessionId(self::THIRD_ID),
            new StoredSession('c', $createdAt, $this->moment('2026-10-05 12:00:00.001')),
        );

        self::assertSame(1, $store->prune($this->moment('2026-10-05 12:00:00')));
        self::assertNull($store->read(new SessionId(self::FIRST_ID)));
        self::assertNotNull($store->read(new SessionId(self::SECOND_ID)));
        self::assertNotNull($store->read(new SessionId(self::THIRD_ID)));
        self::assertSame(2, $store->prune($this->moment('2026-10-05 13:00:00')));
        self::assertSame(0, $store->prune($this->moment('2026-10-05 13:00:00')));
    }

    #[Test]
    public function it_prunes_by_the_expiry_a_touch_gave_a_session(): void
    {
        $store = $this->createStore();
        $store->write(new SessionId(self::FIRST_ID), $this->stored('user 1'));
        $store->touch(new SessionId(self::FIRST_ID), $this->moment('2026-10-05 18:00:00'));

        self::assertSame(0, $store->prune($this->moment('2026-10-05 17:00:00')));
        self::assertNotNull($store->read(new SessionId(self::FIRST_ID)));
    }

    abstract protected function createStore(): SessionStore;

    private function stored(string $payload): StoredSession
    {
        return new StoredSession(
            $payload,
            $this->moment('2026-10-05 10:00:00.125'),
            $this->moment('2026-10-05 14:00:00.375'),
        );
    }

    private function moment(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }

    private function assertStored(StoredSession $expected, ?StoredSession $actual): void
    {
        self::assertNotNull($actual);
        self::assertSame($expected->payload, $actual->payload);
        self::assertSame($this->utc($expected->createdAt), $this->utc($actual->createdAt));
        self::assertSame($this->utc($expected->expiresAt), $this->utc($actual->expiresAt));
    }

    private function utc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }
}
