<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Exception;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\Exception\SessionException;
use Dirthara\Session\Exception\HasExceptionContext;
use Dirthara\Session\Exception\InvalidSessionLifetimeException;

#[CoversClass(InvalidSessionLifetimeException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class InvalidSessionLifetimeExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new InvalidSessionLifetimeException();

        self::assertInstanceOf(SessionException::class, $exception);
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame('', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_keeps_a_previous_exception_and_its_context(): void
    {
        $previous = new InvalidArgumentException('cause');
        $exception = new InvalidSessionLifetimeException('message', 3, $previous, ['lifetime' => 'idle']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['lifetime' => 'idle'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new InvalidSessionLifetimeException(context: ['lifetime' => 'idle', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['lifetime' => 'absolute', 'driver' => 'memory']));
        self::assertSame(['lifetime' => 'absolute', 'kept' => true, 'driver' => 'memory'], $exception->context);
    }

    #[Test]
    public function it_describes_a_lifetime_of_zero(): void
    {
        $exception = InvalidSessionLifetimeException::zero('absolute');

        self::assertSame(
            'Unable to use an absolute session lifetime of zero: a session has to live for longer than that.',
            $exception->getMessage(),
        );
        self::assertSame(['lifetime' => 'absolute'], $exception->context);
    }
}
