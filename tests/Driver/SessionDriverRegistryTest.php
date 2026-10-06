<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Driver;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\Driver\SessionDriverRegistry;
use Dirthara\Session\Exception\HasExceptionContext;
use Dirthara\Session\Tests\Fixtures\RecordingSessionDriver;
use Dirthara\Session\Exception\SessionDriverNotFoundException;
use Dirthara\Session\Exception\DuplicateSessionDriverException;

#[CoversClass(SessionDriverRegistry::class)]
#[UsesClass(SessionDriverNotFoundException::class)]
#[UsesClass(DuplicateSessionDriverException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class SessionDriverRegistryTest extends TestCase
{
    #[Test]
    public function it_provides_the_driver_registered_under_a_name(): void
    {
        $registry = new SessionDriverRegistry();
        $memory = new RecordingSessionDriver();
        $redis = new RecordingSessionDriver();

        $registry->register('memory', $memory);
        $registry->register('redis', $redis);

        self::assertSame($memory, $registry->driver('memory'));
        self::assertSame($redis, $registry->driver('redis'));
    }

    #[Test]
    public function it_has_no_drivers_until_one_is_registered(): void
    {
        self::assertFalse(new SessionDriverRegistry()->has('memory'));
    }

    #[Test]
    public function it_has_each_driver_registered_under_a_name(): void
    {
        $registry = new SessionDriverRegistry();
        $registry->register('memory', new RecordingSessionDriver());
        $registry->register('redis', new RecordingSessionDriver());

        self::assertTrue($registry->has('memory'));
        self::assertTrue($registry->has('redis'));
        self::assertFalse($registry->has('database'));
    }

    #[Test]
    public function it_matches_driver_names_exactly_when_asked_whether_it_has_one(): void
    {
        $registry = new SessionDriverRegistry();
        $registry->register('memory', new RecordingSessionDriver());

        self::assertFalse($registry->has('Memory'));
        self::assertFalse($registry->has(' memory'));
        self::assertFalse($registry->has(''));
    }

    #[Test]
    public function it_rejects_a_second_driver_under_the_same_name(): void
    {
        $registry = new SessionDriverRegistry();
        $first = new RecordingSessionDriver();
        $registry->register('memory', $first);

        try {
            $registry->register('memory', new RecordingSessionDriver());
            self::fail('A second driver under the same name was accepted.');
        } catch (DuplicateSessionDriverException $exception) {
            self::assertSame(['driver' => 'memory'], $exception->context);
        }

        self::assertTrue($registry->has('memory'));
        self::assertSame($first, $registry->driver('memory'));
    }

    #[Test]
    public function it_refuses_a_driver_name_without_a_driver(): void
    {
        $registry = new SessionDriverRegistry();
        $registry->register('memory', new RecordingSessionDriver());

        try {
            $registry->driver('redis');
            self::fail('A driver name without a driver was accepted.');
        } catch (SessionDriverNotFoundException $exception) {
            self::assertSame(['driver' => 'redis'], $exception->context);
        }
    }

    #[Test]
    public function it_matches_driver_names_exactly(): void
    {
        $registry = new SessionDriverRegistry();
        $registry->register('memory', new RecordingSessionDriver());

        $this->expectException(SessionDriverNotFoundException::class);

        $registry->driver('Memory');
    }
}
