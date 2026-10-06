<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests;

use Dirthara\Session\Session;
use PHPUnit\Framework\TestCase;
use Dirthara\Session\SessionManager;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\ValueObject\Duration;
use Dirthara\Session\ValueObject\Lifetime;
use Dirthara\Session\SessionManagerFactory;
use Dirthara\Session\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\Tests\Fixtures\TestClock;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Config\SessionConfiguration;
use Dirthara\Session\Driver\SessionDriverRegistry;
use Dirthara\Session\Exception\HasExceptionContext;
use Dirthara\Session\Driver\Memory\MemorySessionStore;
use Dirthara\Session\Driver\Memory\MemorySessionDriver;
use Dirthara\Session\Serialiser\NativeSessionSerialiser;
use Dirthara\Session\Tests\Fixtures\RecordingSessionDriver;
use Dirthara\Session\Exception\SessionDriverNotFoundException;
use Dirthara\Session\Tests\Fixtures\SequentialSessionIdGenerator;

#[CoversClass(SessionManagerFactory::class)]
#[UsesClass(Session::class)]
#[UsesClass(Duration::class)]
#[UsesClass(Lifetime::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(StoredSession::class)]
#[UsesClass(SessionManager::class)]
#[UsesClass(NativeSessionSerialiser::class)]
#[UsesClass(MemorySessionStore::class)]
#[UsesClass(MemorySessionDriver::class)]
#[UsesClass(SessionConfiguration::class)]
#[UsesClass(SessionDriverRegistry::class)]
#[UsesClass(SessionDriverNotFoundException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class SessionManagerFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_manager_on_a_store_from_the_configured_driver(): void
    {
        $drivers = new SessionDriverRegistry();
        $drivers->register('memory', new MemorySessionDriver());

        $manager = $this->factory($drivers)->create(
            new SessionConfiguration('memory', new Lifetime(Duration::hours(2))),
        );
        $session = $manager->create();
        $session->put('user', 42);
        $manager->save($session);

        self::assertInstanceOf(SessionManager::class, $manager);
        self::assertSame(42, $manager->load($session->id)?->get('user'));
    }

    #[Test]
    public function it_passes_the_configuration_to_the_driver_it_names(): void
    {
        $drivers = new SessionDriverRegistry();
        $memory = new RecordingSessionDriver();
        $redis = new RecordingSessionDriver();
        $drivers->register('memory', $memory);
        $drivers->register('redis', $redis);
        $configuration = new SessionConfiguration('redis', new Lifetime(Duration::hours(2)), ['host' => 'localhost']);

        $this->factory($drivers)->create($configuration);

        self::assertSame([$configuration], $redis->configurations);
        self::assertSame([], $memory->configurations);
    }

    #[Test]
    public function it_gives_the_manager_the_lifetime_of_the_configuration(): void
    {
        $drivers = new SessionDriverRegistry();
        $drivers->register('memory', new MemorySessionDriver());
        $clock = new TestClock();
        $factory = new SessionManagerFactory(
            $drivers,
            new SequentialSessionIdGenerator(),
            new NativeSessionSerialiser(),
            $clock,
        );
        $manager = $factory->create(new SessionConfiguration('memory', new Lifetime(Duration::minutes(30))));
        $session = $manager->create();
        $manager->save($session);

        $clock->advance('+30 minutes');

        self::assertNull($manager->load($session->id));
    }

    #[Test]
    public function it_creates_a_separate_manager_each_time(): void
    {
        $drivers = new SessionDriverRegistry();
        $drivers->register('memory', new MemorySessionDriver());
        $factory = $this->factory($drivers);
        $configuration = new SessionConfiguration('memory', new Lifetime(Duration::hours(2)));

        $first = $factory->create($configuration);
        $second = $factory->create($configuration);
        $session = $first->create();
        $first->save($session);

        self::assertNotSame($first, $second);
        self::assertNull($second->load($session->id));
    }

    #[Test]
    public function it_refuses_a_configuration_naming_a_driver_that_is_not_registered(): void
    {
        $this->expectExceptionObject(SessionDriverNotFoundException::for('redis'));

        $this->factory(new SessionDriverRegistry())->create(
            new SessionConfiguration('redis', new Lifetime(Duration::hours(2))),
        );
    }

    private function factory(SessionDriverRegistry $drivers): SessionManagerFactory
    {
        return new SessionManagerFactory(
            $drivers,
            new SequentialSessionIdGenerator(),
            new NativeSessionSerialiser(),
            new TestClock(),
        );
    }
}
