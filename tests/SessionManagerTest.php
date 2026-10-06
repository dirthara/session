<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests;

use Dirthara\Session\Session;
use PHPUnit\Framework\TestCase;
use Dirthara\Session\SessionManager;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\ValueObject\Duration;
use Dirthara\Session\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\Tests\Fixtures\TestClock;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Config\SessionConfiguration;
use Dirthara\Session\Exception\HasExceptionContext;
use Dirthara\Session\Tests\Fixtures\ForeignSession;
use Dirthara\Session\Driver\Memory\MemorySessionStore;
use Dirthara\Session\Exception\ForeignSessionException;
use Dirthara\Session\Tests\Fixtures\ContextualException;
use Dirthara\Session\Tests\Fixtures\RecordingSessionStore;
use Dirthara\Session\Tests\Fixtures\SequentialSessionIdGenerator;

#[CoversClass(SessionManager::class)]
#[UsesClass(Session::class)]
#[UsesClass(Duration::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(StoredSession::class)]
#[UsesClass(MemorySessionStore::class)]
#[UsesClass(SessionConfiguration::class)]
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
            $this->clock,
            new SessionConfiguration('memory', Duration::hours(2)),
        );
    }

    #[Test]
    public function it_creates_an_empty_session_with_a_new_id_without_storing_it(): void
    {
        $session = $this->manager->create();

        self::assertInstanceOf(Session::class, $session);
        self::assertSame('sessionid-0000000000000000000001', $session->id->value);
        self::assertSame([], $session->values);
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
        $session = $this->manager->create();
        $session->put('user', 42);
        $session->put('flash', null);
        $this->manager->save($session);

        $loaded = $this->manager->load(new SessionId('sessionid-0000000000000000000001'));

        self::assertInstanceOf(Session::class, $loaded);
        self::assertNotSame($session, $loaded);
        self::assertSame('sessionid-0000000000000000000001', $loaded->id->value);
        self::assertSame(['user' => 42, 'flash' => null], $loaded->values);
    }

    #[Test]
    public function it_stores_a_session_until_its_lifetime_has_passed(): void
    {
        $this->manager->save($this->manager->create());

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
            $this->clock,
            new SessionConfiguration('memory', Duration::milliseconds(1500)),
        );

        $manager->save($manager->create());

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
            $this->clock,
            new SessionConfiguration('memory', Duration::hours(400 * 24)),
        );

        $manager->save($manager->create());

        $stored = $this->store->inner->read(new SessionId('sessionid-0000000000000000000001'));
        self::assertNotNull($stored);
        self::assertSame('2027-11-09 12:00:00.250', $stored->expiresAt->format('Y-m-d H:i:s.v'));
    }

    #[Test]
    public function it_extends_the_lifetime_each_time_a_session_is_saved(): void
    {
        $session = $this->manager->create();
        $this->manager->save($session);
        $this->clock->advance('+90 minutes');

        $this->manager->save($session);
        $this->clock->advance('+90 minutes');

        self::assertNotNull($this->manager->load(new SessionId('sessionid-0000000000000000000001')));
    }

    #[Test]
    public function it_loads_a_session_until_the_moment_it_expires(): void
    {
        $this->manager->save($this->manager->create());
        $this->clock->advance('+2 hours -1 millisecond');

        self::assertNotNull($this->manager->load(new SessionId('sessionid-0000000000000000000001')));
    }

    #[Test]
    public function it_deletes_a_session_that_has_expired_instead_of_loading_it(): void
    {
        $this->manager->save($this->manager->create());
        $this->clock->advance('+2 hours');

        self::assertNull($this->manager->load(new SessionId('sessionid-0000000000000000000001')));
        self::assertNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000001')));
        self::assertSame(
            [
                'write sessionid-0000000000000000000001',
                'read sessionid-0000000000000000000001',
                'delete sessionid-0000000000000000000001',
            ],
            $this->store->calls,
        );
    }

    #[Test]
    public function it_moves_a_regenerated_session_to_its_new_id(): void
    {
        $session = $this->manager->create();
        $session->put('user', 42);
        $this->manager->save($session);

        $session->regenerate();
        $this->manager->save($session);

        self::assertNull($this->manager->load(new SessionId('sessionid-0000000000000000000001')));
        self::assertSame(['user' => 42], $this->loaded('sessionid-0000000000000000000002')->values);
        self::assertNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000001')));
    }

    #[Test]
    public function it_writes_the_new_id_before_it_deletes_the_ids_a_session_replaced(): void
    {
        $session = $this->manager->create();
        $session->regenerate();
        $session->regenerate();

        $this->manager->save($session);

        self::assertSame(
            [
                'write sessionid-0000000000000000000003',
                'delete sessionid-0000000000000000000001',
                'delete sessionid-0000000000000000000002',
            ],
            $this->store->calls,
        );
        self::assertInstanceOf(Session::class, $session);
        self::assertSame([], $session->replacedIds);
    }

    #[Test]
    public function it_deletes_the_replaced_ids_only_once(): void
    {
        $session = $this->manager->create();
        $session->regenerate();
        $this->manager->save($session);

        $this->manager->save($session);

        self::assertSame(
            [
                'write sessionid-0000000000000000000002',
                'delete sessionid-0000000000000000000001',
                'write sessionid-0000000000000000000002',
            ],
            $this->store->calls,
        );
    }

    #[Test]
    public function it_stores_an_invalidated_session_empty_under_a_new_id(): void
    {
        $session = $this->manager->create();
        $session->put('user', 42);
        $this->manager->save($session);

        $session->invalidate();
        $this->manager->save($session);

        self::assertNull($this->manager->load(new SessionId('sessionid-0000000000000000000001')));
        self::assertSame([], $this->loaded('sessionid-0000000000000000000002')->values);
    }

    #[Test]
    public function it_regenerates_a_loaded_session_with_its_own_generator(): void
    {
        $this->manager->save($this->manager->create());
        $loaded = $this->loaded('sessionid-0000000000000000000001');

        $loaded->regenerate();

        self::assertSame('sessionid-0000000000000000000002', $loaded->id->value);
    }

    #[Test]
    public function it_keeps_the_replaced_ids_when_the_store_fails_to_write(): void
    {
        $session = $this->manager->create();
        $session->regenerate();
        $this->store->throwingOperations = ['write'];

        try {
            $this->manager->save($session);
            self::fail('A failed write was not reported.');
        } catch (ContextualException) {
            self::assertSame(['write sessionid-0000000000000000000002'], $this->store->calls);
        }

        self::assertInstanceOf(Session::class, $session);
        self::assertSame(['sessionid-0000000000000000000001'], [$session->replacedIds[0]->value]);
    }

    #[Test]
    public function it_deletes_the_replaced_ids_on_the_next_save_when_the_store_fails_to_delete(): void
    {
        $session = $this->manager->create();
        $session->regenerate();
        $this->store->throwingOperations = ['delete'];

        try {
            $this->manager->save($session);
            self::fail('A failed delete was not reported.');
        } catch (ContextualException) {
            self::assertNotNull($this->store->inner->read(new SessionId('sessionid-0000000000000000000002')));
        }

        $this->store->throwingOperations = [];
        $this->manager->save($session);

        self::assertSame(
            [
                'write sessionid-0000000000000000000002',
                'delete sessionid-0000000000000000000001',
                'write sessionid-0000000000000000000002',
                'delete sessionid-0000000000000000000001',
            ],
            $this->store->calls,
        );
        self::assertInstanceOf(Session::class, $session);
        self::assertSame([], $session->replacedIds);
    }

    #[Test]
    public function it_refuses_to_save_a_session_it_did_not_create_or_load(): void
    {
        try {
            $this->manager->save(new ForeignSession(new SessionId('sessionid-foreign-0000000000000001')));
            self::fail('A foreign session was saved.');
        } catch (ForeignSessionException $exception) {
            self::assertSame(['class' => ForeignSession::class], $exception->context);
        }

        self::assertSame([], $this->store->calls);
    }

    private function loaded(string $id): Session
    {
        $session = $this->manager->load(new SessionId($id));
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
