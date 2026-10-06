<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Exception;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\Exception\SessionException;
use Dirthara\Session\Exception\HasExceptionContext;
use Dirthara\Session\Exception\SessionSerialisationException;

#[CoversClass(SessionSerialisationException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class SessionSerialisationExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new SessionSerialisationException();

        self::assertInstanceOf(SessionException::class, $exception);
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_keeps_a_previous_exception_and_its_context(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new SessionSerialisationException('message', 3, $previous, ['type' => 'Closure']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['type' => 'Closure'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new SessionSerialisationException(context: ['type' => 'Closure', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['type' => 'Replaced', 'key' => 'user']));
        self::assertSame(['type' => 'Replaced', 'kept' => true, 'key' => 'user'], $exception->context);
    }

    #[Test]
    public function it_describes_values_that_cannot_be_serialised(): void
    {
        $previous = new RuntimeException('cause');
        $exception = SessionSerialisationException::unableToSerialise($previous);

        self::assertSame(
            'Unable to serialise the session values: one of them cannot be serialised.',
            $exception->getMessage(),
        );
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_describes_a_malformed_payload(): void
    {
        $previous = new RuntimeException('cause');
        $exception = SessionSerialisationException::unableToDeserialise($previous);

        self::assertSame(
            'Unable to deserialise a session payload: the payload is malformed.',
            $exception->getMessage(),
        );
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_describes_a_payload_of_a_class_that_does_not_exist(): void
    {
        $exception = SessionSerialisationException::unknownClass();

        self::assertSame(
            'Unable to deserialise a session payload: it holds an object of a class that does not exist.',
            $exception->getMessage(),
        );
        self::assertNull($exception->getPrevious());
    }

    #[Test]
    public function it_describes_a_payload_that_does_not_hold_session_values(): void
    {
        $exception = SessionSerialisationException::notSessionValues("class@anonymous\0/app/src/Value.php:3$0");

        self::assertSame(
            'Unable to deserialise a session payload: it holds a value of type "class@anonymous\\000/app/src/Value.php:3$0" rather than an array of session values.',
            $exception->getMessage(),
        );
        self::assertSame(['type' => 'class@anonymous\\000/app/src/Value.php:3$0'], $exception->context);
    }
}
