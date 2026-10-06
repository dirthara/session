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
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function it_accepts_the_shortest_and_the_longest_idle_lifetime(): void
    {
        self::assertSame(1, new Lifetime(Duration::milliseconds(1))->idle->milliseconds);
        self::assertSame(Lifetime::MAXIMUM_MILLISECONDS, new Lifetime(Duration::hours(400 * 24))->idle->milliseconds);
    }

    /**
     * @return iterable<string, array{Duration}>
     */
    public static function invalidDurations(): iterable
    {
        yield 'zero' => [Duration::milliseconds(0)];
        yield 'a millisecond over 400 days' => [Duration::milliseconds(Lifetime::MAXIMUM_MILLISECONDS + 1)];
        yield 'the longest duration' => [Duration::milliseconds(PHP_INT_MAX)];
    }

    #[Test]
    #[DataProvider('invalidDurations')]
    public function it_refuses_an_idle_lifetime_that_is_zero_or_longer_than_400_days(Duration $idle): void
    {
        try {
            new Lifetime($idle);
            self::fail('An invalid idle lifetime was accepted.');
        } catch (InvalidSessionLifetimeException $exception) {
            self::assertSame(
                [
                    'lifetime' => 'idle',
                    'milliseconds' => $idle->milliseconds,
                    'maximum' => Lifetime::MAXIMUM_MILLISECONDS,
                ],
                $exception->context,
            );
        }
    }
}
