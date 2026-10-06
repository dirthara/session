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
use Dirthara\Session\Exception\InvalidSessionIdException;

#[CoversClass(InvalidSessionIdException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class InvalidSessionIdExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new InvalidSessionIdException();

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
        $exception = new InvalidSessionIdException('message', 3, $previous, ['length' => 4]);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['length' => 4], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new InvalidSessionIdException(context: ['length' => 4, 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['length' => 5, 'cookie' => 'session']));
        self::assertSame(['length' => 5, 'kept' => true, 'cookie' => 'session'], $exception->context);
    }

    #[Test]
    public function it_describes_a_malformed_session_id_by_its_length_only(): void
    {
        $exception = InvalidSessionIdException::malformed(4);

        self::assertSame(
            'Unable to use the session ID of 4 bytes: a session ID has to be 16 to 256 letters, digits, hyphens, or underscores.',
            $exception->getMessage(),
        );
        self::assertSame(['length' => 4], $exception->context);
    }
}
