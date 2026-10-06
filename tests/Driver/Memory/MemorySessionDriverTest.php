<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Driver\Memory;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\ValueObject\Duration;
use Dirthara\Session\ValueObject\Lifetime;
use Dirthara\Session\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Config\SessionConfiguration;
use Dirthara\Session\Driver\Memory\MemorySessionStore;
use Dirthara\Session\Driver\Memory\MemorySessionDriver;

#[CoversClass(MemorySessionDriver::class)]
#[UsesClass(Duration::class)]
#[UsesClass(Lifetime::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(StoredSession::class)]
#[UsesClass(MemorySessionStore::class)]
#[UsesClass(SessionConfiguration::class)]
final class MemorySessionDriverTest extends TestCase
{
    #[Test]
    public function it_creates_a_memory_store(): void
    {
        $store = new MemorySessionDriver()->create(
            new SessionConfiguration('memory', new Lifetime(Duration::hours(2))),
        );

        self::assertInstanceOf(MemorySessionStore::class, $store);
    }

    #[Test]
    public function it_creates_a_separate_store_each_time(): void
    {
        $driver = new MemorySessionDriver();
        $configuration = new SessionConfiguration('memory', new Lifetime(Duration::hours(2)));
        $first = $driver->create($configuration);
        $second = $driver->create($configuration);
        $id = new SessionId('11111111111111111111111111111111');

        $first->write(
            $id,
            new StoredSession(
                'user 42',
                new DateTimeImmutable('2026-10-05 10:00:00'),
                new DateTimeImmutable('2026-10-05 14:00:00'),
            ),
        );

        self::assertNotSame($first, $second);
        self::assertNull($second->read($id));
    }

    #[Test]
    public function it_ignores_the_options_of_the_configuration(): void
    {
        $store = new MemorySessionDriver()->create(new SessionConfiguration(
            'memory',
            new Lifetime(Duration::hours(2)),
            ['path' => [
                'not',
                'a',
                'string',
            ]],
        ));

        self::assertInstanceOf(MemorySessionStore::class, $store);
    }
}
