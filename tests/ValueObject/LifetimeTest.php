<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\ValueObject;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\ValueObject\Duration;
use Dirthara\Session\ValueObject\Lifetime;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\Exception\HasExceptionContext;
use Dirthara\Session\Exception\InvalidSessionLifetimeException;

use const PHP_INT_MAX;

#[CoversClass(Lifetime::class)]
#[UsesClass(Duration::class)]
#[UsesClass(InvalidSessionLifetimeException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class LifetimeTest extends TestCase
{
    #[Test]
    public function it_carries_the_idle_lifetime(): void
    {
        $idle = Duration::minutes(30);

        self::assertSame($idle, new Lifetime($idle)->idle);
    }

    #[Test]
    public function it_has_no_absolute_lifetime_by_default(): void
    {
        self::assertNull(new Lifetime(Duration::minutes(30))->absolute);
    }

    #[Test]
    public function it_carries_the_absolute_lifetime(): void
    {
        $absolute = Duration::hours(12);

        self::assertSame($absolute, new Lifetime(Duration::minutes(30), $absolute)->absolute);
    }

    #[Test]
    public function it_accepts_an_absolute_lifetime_shorter_than_the_idle_lifetime(): void
    {
        self::assertSame(1, new Lifetime(Duration::hours(2), Duration::milliseconds(1))->absolute?->milliseconds);
    }

    #[Test]
    public function it_accepts_the_longest_duration_as_either_lifetime(): void
    {
        $longest = Duration::milliseconds(PHP_INT_MAX);
        $lifetime = new Lifetime($longest, $longest);

        self::assertSame($longest, $lifetime->idle);
        self::assertSame($longest, $lifetime->absolute);
    }

    #[Test]
    public function it_refuses_an_absolute_lifetime_of_zero(): void
    {
        try {
            new Lifetime(Duration::hours(2), Duration::milliseconds(0));
            self::fail('An absolute lifetime of zero was accepted.');
        } catch (InvalidSessionLifetimeException $exception) {
            self::assertSame(['lifetime' => 'absolute'], $exception->context);
        }
    }

    #[Test]
    public function it_accepts_the_shortest_lifetimes(): void
    {
        $lifetime = new Lifetime(Duration::milliseconds(1), Duration::milliseconds(1));

        self::assertSame(1, $lifetime->idle->milliseconds);
        self::assertSame(1, $lifetime->absolute?->milliseconds);
    }

    #[Test]
    public function it_refuses_an_idle_lifetime_of_zero(): void
    {
        try {
            new Lifetime(Duration::milliseconds(0));
            self::fail('An idle lifetime of zero was accepted.');
        } catch (InvalidSessionLifetimeException $exception) {
            self::assertSame(['lifetime' => 'idle'], $exception->context);
        }
    }
}
