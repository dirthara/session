<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests;

use stdClass;
use DateTimeImmutable;
use Dirthara\Session\Session;
use PHPUnit\Framework\TestCase;
use Dirthara\Session\SessionState;
use Dirthara\Session\SessionManager;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\ValueObject\Duration;
use Dirthara\Session\ValueObject\Lifetime;
use Dirthara\Session\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\Tests\Fixtures\TestClock;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Exception\HasExceptionContext;
use Dirthara\Session\Driver\Memory\MemorySessionStore;
use Dirthara\Session\Exception\ForeignSessionException;
use Dirthara\Session\Serialiser\NativeSessionSerialiser;
use Dirthara\Session\Tests\Fixtures\ContextualException;
use Dirthara\Session\Tests\Fixtures\RecordingSessionStore;
use Dirthara\Session\Exception\SessionSerialisationException;
use Dirthara\Session\Tests\Fixtures\SequentialSessionIdGenerator;

#[CoversClass(SessionManager::class)]
#[UsesClass(Session::class)]
#[UsesClass(SessionState::class)]
#[UsesClass(Duration::class)]
#[UsesClass(Lifetime::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(StoredSession::class)]
#[UsesClass(NativeSessionSerialiser::class)]
#[UsesClass(SessionSerialisationException::class)]
#[UsesClass(MemorySessionStore::class)]
#[UsesClass(ForeignSessionException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class SessionManagerTest extends TestCase
{
    private RecordingSessionStore $store;

    private TestClock $clock;

    private SessionManager $manager;

    protected function setUp(): void
    {
        $this->store = new RecordingSessionStore();
        $this->clock = new TestClock('2026-10-05 12:00:00.250');
        $this->manager = new SessionManager(
            $this->store,
            new SequentialSessionIdGenerator(),
            new NativeSessionSerialiser(),
            $this->clock,
            new Lifetime(Duration::hours(2)),
        );
    }

    #[Test]
    public function it_creates_an_empty_session_with_a_new_id_without_storing_it(): void
    {
        $session = $this->manager->create();

        self::assertSame('sessionid-0000000000000000000001', $session->id->value);
        self::assertFalse($session->has('user'));
        self::assertSame([], $this->store->calls);
    }

    #[Test]
    public function it_creates_a_session_with_another_id_each_time(): void
    {
        self::assertNotEquals($this->manager->create()->id, $this->manager->create()->id);
    }

    #[Test]
    public function it_loads_nothing_for_an_id_without_a_session(): void
    {
        self::assertNull($this->manager->load(new SessionId('sessionid-unknown-0000000000000001')));
        self::assertSame(['read sessionid-unknown-0000000000000001'], $this->store->calls);
    }

    #[Test]
    public function it_loads_the_values_of_a_saved_session_under_its_id(): void
    {
        $session = $this->filled();
        $session->put('user', 42);
        $session->put('flash', null);
        $this->manager->save($session);

        $loaded = $this->manager->load(new SessionId('sessionid-0000000000000000000001'));

        self::assertNotNull($loaded);
        self::assertNotSame($session, $loaded);
        self::assertSame('sessionid-0000000000000000000001', $loaded->id->value);
        self::assertSame(42, $loaded->get('user'));
        self::assertTrue($loaded->has('flash'));
    }

    #[Test]
    public function it_stores_the_values_as_a_serialised_payload(): void
    {
        $session = $this->filled();
        $session->put('user', 42);

        $this->manager->save($session);

        self::assertSame(
            'a:1:{s:4:"user";i:42;}',
            $this->store->inner->read(new SessionId('sessionid-0000000000000000000001'))?->payload,
        );
    }

    #[Test]
    public function it_keeps_a_change_to_a_stored_object_out_of_the_store_until_it_is_saved(): void
    {
        $user = new stdClass();
        $user->name = 'Ada';
        $session = $this->filled();
        $session->put('user', $user);
        $this->manager->save($session);

        $user->name = 'Grace';

        // @mago-expect analysis:mixed-assignment The assertion narrows the loaded value
        $loaded = $this->loaded('sessionid-0000000000000000000001')->get('user');
        self::assertInstanceOf(stdClass::class, $loaded);
        self::assertSame('Ada', $loaded->name);
    }

    #[Test]
    public function it_loads_nothing_for_a_payload_it_cannot_deserialise(): void
    {
        $id = new SessionId('sessionid-0000000000000000000009');
        $this->store->inner->write(
            $id,
            new StoredSession(
                'not a payload',
                new DateTimeImmutable('2026-10-05 10:00:00'),
                new DateTimeImmutable('2026-10-05 14:00:00'),
            ),
        );

        self::assertNull($this->manager->load($id));
    }

    #[Test]
    public function it_refuses_a_value_it_cannot_serialise_before_it_touches_the_store(): void
    {
        $session = $this->filled();
        $session->put('callback', static fn(): int => 42);

        try {
            $this->manager->save($session);
            self::fail('A value that cannot be serialised was saved.');
        } catch (SessionSerialisationException) {
            self::assertSame([], $this->store->calls);
        }
    }

    #[Test]
    public function it_stores_a_session_until_its_lifetime_has_passed(): void
    {
        $this->manager->save($this->filled());

        $stored = $this->store->inner->read(new SessionId('sessionid-0000000000000000000001'));

        self::assertNotNull($stored);
        self::assertSame('2026-10-05 14:00:00.250', $stored->expiresAt->format('Y-m-d H:i:s.v'));
    }

    #[Test]
    public function it_keeps_a_session_for_its_lifetime_in_milliseconds(): void
    {
        $manager = new SessionManager(
            $this->store,
            new SequentialSessionIdGenerator(),
            new NativeSessionSerialiser(),
            $this->clock,
            new Lifetime(Duration::milliseconds(1500)),
        );

        $session = $manager->create();
        $session->put('user', 42);
        $manager->save($session);

        $stored = $this->store->inner->read(new SessionId('sessionid-0000000000000000000001'));
        self::assertNotNull($stored);
        self::assertSame('2026-10-05 12:00:01.750', $stored->expiresAt->format('Y-m-d H:i:s.v'));
    }

    #[Test]
    public function it_keeps_a_session_for_the_longest_lifetime(): void
    {
        $manager = new SessionManager(
            $this->store,
            new SequentialSessionIdGenerator(),
            new NativeSessionSerialiser(),
            $this->clock,
            new Lifetime(Duration::hours(400 * 24)),
        );

        $session = $manager->create();
        $session->put('user', 42);
        $manager->save($session);

        $stored = $this->store->inner->read(new SessionId('sessionid-0000000000000000000001'));
        self::assertNotNull($stored);
        self::assertSame('2027-11-09 12:00:00.250', $stored->expiresAt->format('Y-m-d H:i:s.v'));
    }

    #[Test]
    public function it_stores_the_moment_a_session_was_first_stored_and_keeps_it(): void
    {
        $session = $this->filled();
        $this->manager->save($session);
        $this->clock->advance('+1 hour');
        $session->put('locale', 'en_GB');
        $this->manager->save($session);

        $stored = $this->store->inner->read(new SessionId('sessionid-0000000000000000000001'));
        self::assertNotNull($stored);
        self::assertSame('2026-10-05 12:00:00.250', $stored->createdAt->format('Y-m-d H:i:s.v'));
        self::assertSame('2026-10-05 15:00:00.250', $stored->expiresAt->format('Y-m-d H:i:s.v'));
    }

    #[Test]
    public function it_never_lets_a_session_live_past_its_absolute_lifetime(): void
    {
        $manager = $this->manager(new Lifetime(Duration::hours(2), Duration::hours(3)));
        $session = $manager->create();
        $session->put('user', 42);
        $manager->save($session);
        $this->clock->advance('+90 minutes');

        $manager->save($session);

        $stored = $this->store->inner->read(new SessionId('sessionid-0000000000000000000001'));
        self::assertNotNull($stored);
        self::assertSame('2026-10-05 15:00:00.250', $stored->expiresAt->format('Y-m-d H:i:s.v'));
    }

    #[Test]
    public function it_loads_nothing_once_the_absolute_lifetime_has_passed_however_often_it_was_saved(): void
    {
        $manager = $this->manager(new Lifetime(Duration::hours(2), Duration::hours(3)));
        $session = $manager->create();
        $session->put('user', 42);
        $manager->save($session);

        for ($i = 0; $i < 5; $i++) {
            $this->clock->advance('+30 minutes');
            $manager->save($session);
        }

        $this->clock->advance('+30 minutes');

        self::assertNull($manager->load(new SessionId('sessionid-0000000000000000000001')));
    }

    #[Test]
    public function it_deletes_instead_of_saving_a_session_whose_absolute_lifetime_has_passed(): void
    {
        $manager = $this->manager(new Lifetime(Duration::hours(2), Duration::hours(3)));
        $session = $manager->create();
        $session->put('user', 42);
        $manager->save($session);
        $this->clock->advance('+90 minutes');
        $manager->save($session);
        $loaded = $manager->load(new SessionId('sessionid-0000000000000000000001'));
        self::assertNotNull($loaded);
        $this->clock->advance('+90 minutes');

        self::assertFalse($manager->save($loaded));
        self::assertNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000001')));
    }

    #[Test]
    public function it_keeps_the_absolute_lifetime_when_a_session_is_regenerated(): void
    {
        $manager = $this->manager(new Lifetime(Duration::hours(2), Duration::hours(3)));
        $session = $manager->create();
        $session->put('user', 42);
        $manager->save($session);
        $this->clock->advance('+2 hours');

        $manager->regenerate($session);
        $manager->save($session);

        $stored = $this->store->inner->read(new SessionId('sessionid-0000000000000000000002'));
        self::assertNotNull($stored);
        self::assertSame('2026-10-05 12:00:00.250', $stored->createdAt->format('Y-m-d H:i:s.v'));
        self::assertSame('2026-10-05 15:00:00.250', $stored->expiresAt->format('Y-m-d H:i:s.v'));
    }

    #[Test]
    public function it_starts_the_absolute_lifetime_again_when_a_session_is_invalidated(): void
    {
        $manager = $this->manager(new Lifetime(Duration::hours(2), Duration::hours(3)));
        $session = $manager->create();
        $session->put('user', 42);
        $manager->save($session);
        $this->clock->advance('+2 hours');

        $manager->invalidate($session);
        $session->put('flash', 'Logged out');
        $manager->save($session);

        $stored = $this->store->inner->read(new SessionId('sessionid-0000000000000000000002'));
        self::assertNotNull($stored);
        self::assertSame('2026-10-05 14:00:00.250', $stored->createdAt->format('Y-m-d H:i:s.v'));
        self::assertSame('2026-10-05 16:00:00.250', $stored->expiresAt->format('Y-m-d H:i:s.v'));
    }

    #[Test]
    public function it_extends_the_lifetime_each_time_a_session_is_saved(): void
    {
        $session = $this->filled();
        $this->manager->save($session);
        $this->clock->advance('+90 minutes');

        $this->manager->save($session);
        $this->clock->advance('+90 minutes');

        self::assertNotNull($this->manager->load(new SessionId('sessionid-0000000000000000000001')));
    }

    #[Test]
    public function it_loads_a_session_until_the_moment_it_expires(): void
    {
        $this->manager->save($this->filled());
        $this->clock->advance('+2 hours -1 millisecond');

        self::assertNotNull($this->manager->load(new SessionId('sessionid-0000000000000000000001')));
    }

    #[Test]
    public function it_loads_nothing_for_an_expired_session_and_leaves_it_to_be_pruned(): void
    {
        $this->manager->save($this->filled());
        $this->clock->advance('+2 hours');

        self::assertNull($this->manager->load(new SessionId('sessionid-0000000000000000000001')));
        self::assertNotNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000001')));
        self::assertSame(
            ['write sessionid-0000000000000000000001', 'read sessionid-0000000000000000000001'],
            $this->store->calls,
        );
    }

    #[Test]
    public function it_prunes_the_sessions_that_have_expired_by_now(): void
    {
        $expiring = $this->filled();
        $this->manager->save($expiring);
        $this->clock->advance('+1 hour');
        $this->manager->save($this->filled());
        $this->clock->advance('+1 hour');

        self::assertSame(1, $this->manager->prune());
        self::assertSame('prune 2026-10-05 14:00:00.250', $this->store->calls[2]);
        self::assertNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000001')));
        self::assertNotNull($this->manager->load(new SessionId('sessionid-0000000000000000000002')));
    }

    #[Test]
    public function it_regenerates_the_id_at_once_and_keeps_the_values(): void
    {
        $session = $this->filled();
        $session->put('user', 42);

        $this->manager->regenerate($session);

        self::assertSame('sessionid-0000000000000000000002', $session->id->value);
        self::assertSame(42, $session->get('user'));
        self::assertSame([], $this->store->calls);
    }

    #[Test]
    public function it_moves_a_regenerated_session_to_its_new_id_on_save(): void
    {
        $session = $this->filled();
        $session->put('user', 42);
        $this->manager->save($session);

        $this->manager->regenerate($session);
        $this->manager->save($session);

        self::assertNull($this->manager->load(new SessionId('sessionid-0000000000000000000001')));
        self::assertSame(42, $this->loaded('sessionid-0000000000000000000002')->get('user'));
    }

    #[Test]
    public function it_replaces_a_stored_session_under_the_same_id(): void
    {
        $session = $this->filled();
        $this->manager->save($session);
        $session->put('user', 42);

        self::assertTrue($this->manager->save($session));
        self::assertSame(
            ['write sessionid-0000000000000000000001', 'replace sessionid-0000000000000000000001'],
            $this->store->calls,
        );
    }

    #[Test]
    public function it_only_extends_the_expiry_of_a_session_whose_values_did_not_change(): void
    {
        $this->manager->save($this->filled());
        $this->clock->advance('+1 hour');
        $loaded = $this->loaded('sessionid-0000000000000000000001');
        $loaded->get('user');

        self::assertTrue($this->manager->save($loaded));

        $stored = $this->store->inner->read(new SessionId('sessionid-0000000000000000000001'));
        self::assertNotNull($stored);
        self::assertSame('a:1:{s:4:"user";i:42;}', $stored->payload);
        self::assertSame('2026-10-05 15:00:00.250', $stored->expiresAt->format('Y-m-d H:i:s.v'));
        self::assertSame(
            [
                'write sessionid-0000000000000000000001',
                'read sessionid-0000000000000000000001',
                'touch sessionid-0000000000000000000001',
            ],
            $this->store->calls,
        );
    }

    /**
     * @return iterable<string, array{callable(Session): void}>
     */
    public static function changes(): iterable
    {
        yield 'a put' => [static fn(Session $session) => $session->put('locale', 'en_GB')];
        yield 'a put of the same value' => [static fn(Session $session) => $session->put('user', 42)];
        yield 'a removal' => [static fn(Session $session) => $session->remove('locale')];
    }

    /**
     * @param callable(Session): void $change
     */
    #[Test]
    #[DataProvider('changes')]
    public function it_replaces_a_session_whose_values_changed(callable $change): void
    {
        $session = $this->filled();
        $this->manager->save($session);
        $loaded = $this->loaded('sessionid-0000000000000000000001');

        $change($loaded);

        self::assertTrue($this->manager->save($loaded));
        self::assertSame('replace sessionid-0000000000000000000001', $this->store->calls[2]);
    }

    #[Test]
    public function it_touches_a_session_again_once_its_changes_are_saved(): void
    {
        $session = $this->filled();
        $this->manager->save($session);
        $session->put('locale', 'en_GB');
        $this->manager->save($session);

        $this->manager->save($session);

        self::assertSame(
            [
                'write sessionid-0000000000000000000001',
                'replace sessionid-0000000000000000000001',
                'touch sessionid-0000000000000000000001',
            ],
            $this->store->calls,
        );
    }

    #[Test]
    public function it_does_not_touch_a_loaded_session_that_was_deleted_from_the_store(): void
    {
        $this->manager->save($this->filled());
        $loaded = $this->loaded('sessionid-0000000000000000000001');
        $this->store->inner->delete(new SessionId('sessionid-0000000000000000000001'));

        self::assertFalse($this->manager->save($loaded));
        self::assertNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000001')));
    }

    #[Test]
    public function it_keeps_a_failed_change_for_the_next_save(): void
    {
        $session = $this->filled();
        $this->manager->save($session);
        $session->put('locale', 'en_GB');
        $this->store->throwingOperations = ['replace'];

        try {
            $this->manager->save($session);
            self::fail('A failed replace was not reported.');
        } catch (ContextualException) {
            $this->store->throwingOperations = [];
        }

        $this->manager->save($session);

        self::assertSame('en_GB', $this->loaded('sessionid-0000000000000000000001')->get('locale'));
    }

    #[Test]
    public function it_deletes_the_stored_id_before_it_writes_the_new_one(): void
    {
        $session = $this->filled();
        $this->manager->save($session);
        $this->manager->regenerate($session);
        $this->manager->regenerate($session);

        self::assertTrue($this->manager->save($session));
        self::assertSame(
            [
                'write sessionid-0000000000000000000001',
                'delete sessionid-0000000000000000000001',
                'write sessionid-0000000000000000000003',
            ],
            $this->store->calls,
        );
    }

    #[Test]
    public function it_touches_the_new_id_once_a_regenerated_session_is_saved(): void
    {
        $session = $this->filled();
        $this->manager->save($session);
        $this->manager->regenerate($session);
        $this->manager->save($session);

        $this->manager->save($session);

        self::assertSame(
            [
                'write sessionid-0000000000000000000001',
                'delete sessionid-0000000000000000000001',
                'write sessionid-0000000000000000000002',
                'touch sessionid-0000000000000000000002',
            ],
            $this->store->calls,
        );
    }

    #[Test]
    public function it_does_not_bring_back_a_session_that_another_request_regenerated(): void
    {
        $this->manager->save($this->filled());
        $first = $this->loaded('sessionid-0000000000000000000001');
        $second = $this->loaded('sessionid-0000000000000000000001');
        $this->manager->regenerate($first);
        $this->manager->save($first);

        $second->put('user', 42);

        self::assertFalse($this->manager->save($second));
        self::assertNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000001')));
    }

    #[Test]
    public function it_does_not_bring_back_a_session_that_another_request_invalidated(): void
    {
        $session = $this->filled();
        $session->put('user', 42);
        $this->manager->save($session);
        $loggingOut = $this->loaded('sessionid-0000000000000000000001');
        $concurrent = $this->loaded('sessionid-0000000000000000000001');
        $this->manager->invalidate($loggingOut);
        $this->manager->save($loggingOut);

        self::assertFalse($this->manager->save($concurrent));
        self::assertNull($this->manager->load(new SessionId('sessionid-0000000000000000000001')));
    }

    #[Test]
    public function it_does_not_store_a_new_id_for_a_session_that_another_request_regenerated(): void
    {
        $this->manager->save($this->filled());
        $first = $this->loaded('sessionid-0000000000000000000001');
        $second = $this->loaded('sessionid-0000000000000000000001');
        $this->manager->regenerate($first);
        $this->manager->save($first);
        $this->manager->regenerate($second);

        self::assertFalse($this->manager->save($second));
        self::assertNull($this->store->inner->read($second->id));
    }

    #[Test]
    public function it_does_not_store_a_loaded_session_that_was_deleted_from_the_store(): void
    {
        $this->manager->save($this->filled());
        $session = $this->loaded('sessionid-0000000000000000000001');
        $this->store->inner->delete(new SessionId('sessionid-0000000000000000000001'));

        self::assertFalse($this->manager->save($session));
        self::assertFalse($this->manager->save($session));
        self::assertNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000001')));
    }

    #[Test]
    public function it_leaves_the_session_stored_when_the_store_fails_to_delete_the_old_id(): void
    {
        $session = $this->filled();
        $this->manager->save($session);
        $this->manager->regenerate($session);
        $this->store->throwingOperations = ['delete'];

        try {
            $this->manager->save($session);
            self::fail('A failed delete was not reported.');
        } catch (ContextualException) {
            self::assertNotNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000001')));
            self::assertNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000002')));
        }

        $this->store->throwingOperations = [];

        self::assertTrue($this->manager->save($session));
        self::assertNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000001')));
        self::assertNotNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000002')));
    }

    #[Test]
    public function it_writes_the_new_id_on_the_next_save_when_the_store_fails_to_write_it(): void
    {
        $session = $this->filled();
        $this->manager->save($session);
        $this->manager->regenerate($session);
        $this->store->throwingOperations = ['write'];

        try {
            $this->manager->save($session);
            self::fail('A failed write was not reported.');
        } catch (ContextualException) {
            self::assertNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000001')));
        }

        $this->store->throwingOperations = [];

        self::assertTrue($this->manager->save($session));
        self::assertNotNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000002')));
    }

    #[Test]
    public function it_deletes_nothing_when_a_new_session_is_regenerated_before_its_first_save(): void
    {
        $session = $this->filled();
        $this->manager->regenerate($session);

        self::assertTrue($this->manager->save($session));
        self::assertSame(['write sessionid-0000000000000000000002'], $this->store->calls);
    }

    #[Test]
    public function it_invalidates_by_clearing_the_values_and_regenerating_the_id(): void
    {
        $session = $this->filled();
        $this->manager->save($session);

        $this->manager->invalidate($session);

        self::assertSame('sessionid-0000000000000000000002', $session->id->value);
        self::assertFalse($session->has('user'));
        self::assertFalse($this->manager->save($session));
        self::assertNull($this->manager->load(new SessionId('sessionid-0000000000000000000001')));
        self::assertNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000002')));
    }

    #[Test]
    public function it_stores_an_invalidated_session_under_its_new_id_once_it_has_values_again(): void
    {
        $session = $this->filled();
        $this->manager->save($session);
        $this->manager->invalidate($session);
        $session->put('locale', 'en_GB');

        self::assertTrue($this->manager->save($session));
        self::assertNull($this->manager->load(new SessionId('sessionid-0000000000000000000001')));
        self::assertSame('en_GB', $this->loaded('sessionid-0000000000000000000002')->get('locale'));
        self::assertFalse($this->loaded('sessionid-0000000000000000000002')->has('user'));
    }

    #[Test]
    public function it_does_not_store_a_new_session_without_values(): void
    {
        $session = $this->manager->create();

        self::assertFalse($this->manager->save($session));
        self::assertSame([], $this->store->calls);
    }

    #[Test]
    public function it_stores_a_new_session_once_it_has_values(): void
    {
        $session = $this->manager->create();
        $this->manager->save($session);
        $session->put('user', 42);

        self::assertTrue($this->manager->save($session));
        self::assertSame(['write sessionid-0000000000000000000001'], $this->store->calls);
    }

    #[Test]
    public function it_deletes_a_stored_session_whose_values_were_cleared(): void
    {
        $session = $this->filled();
        $this->manager->save($session);
        $session->clear();

        self::assertFalse($this->manager->save($session));
        self::assertNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000001')));
        self::assertSame(
            ['write sessionid-0000000000000000000001', 'delete sessionid-0000000000000000000001'],
            $this->store->calls,
        );
    }

    #[Test]
    public function it_stores_a_cleared_session_again_once_it_has_values_again(): void
    {
        $session = $this->filled();
        $this->manager->save($session);
        $session->clear();
        $this->manager->save($session);
        $session->put('user', 43);

        self::assertTrue($this->manager->save($session));
        self::assertSame(43, $this->loaded('sessionid-0000000000000000000001')->get('user'));
    }

    #[Test]
    public function it_regenerates_a_loaded_session(): void
    {
        $this->manager->save($this->filled());
        $loaded = $this->loaded('sessionid-0000000000000000000001');

        $this->manager->regenerate($loaded);

        self::assertSame('sessionid-0000000000000000000002', $loaded->id->value);
    }

    /**
     * @return iterable<string, array{callable(SessionManager, Session): void, string}>
     */
    public static function operations(): iterable
    {
        yield 'save' => [static fn(SessionManager $manager, Session $session) => $manager->save($session), 'save'];
        yield 'regenerate' => [
            static fn(SessionManager $manager, Session $session) => $manager->regenerate($session),
            'regenerate',
        ];
        yield 'invalidate' => [
            static fn(SessionManager $manager, Session $session) => $manager->invalidate($session),
            'invalidate',
        ];
    }

    /**
     * @param callable(SessionManager, Session): void $operation
     */
    #[Test]
    #[DataProvider('operations')]
    public function it_refuses_a_session_it_did_not_create_or_load(callable $operation, string $name): void
    {
        $session = new Session(new SessionState(new SessionId('sessionid-chosen-00000000000000001'), storedId: null));

        try {
            $operation($this->manager, $session);
            self::fail('A session the manager did not create or load was accepted.');
        } catch (ForeignSessionException $exception) {
            self::assertSame(['operation' => $name], $exception->context);
        }

        self::assertSame('sessionid-chosen-00000000000000001', $session->id->value);
        self::assertSame([], $this->store->calls);
    }

    #[Test]
    public function it_refuses_a_session_another_manager_created(): void
    {
        $other = new SessionManager(
            new MemorySessionStore(),
            new SequentialSessionIdGenerator(),
            new NativeSessionSerialiser(),
            $this->clock,
            new Lifetime(Duration::hours(2)),
        );

        $this->expectExceptionObject(ForeignSessionException::unknown('save'));

        $this->manager->save($other->create());
    }

    private function manager(Lifetime $lifetime): SessionManager
    {
        return new SessionManager(
            $this->store,
            new SequentialSessionIdGenerator(),
            new NativeSessionSerialiser(),
            $this->clock,
            $lifetime,
        );
    }

    private function filled(): Session
    {
        $session = $this->manager->create();
        $session->put('user', 42);

        return $session;
    }

    private function loaded(string $id): Session
    {
        $session = $this->manager->load(new SessionId($id));
        self::assertNotNull($session);

        return $session;
    }
}
