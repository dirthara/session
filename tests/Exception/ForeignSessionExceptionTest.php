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
use Dirthara\Session\Exception\ForeignSessionException;

#[CoversClass(ForeignSessionException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class ForeignSessionExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new ForeignSessionException();

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
        $exception = new ForeignSessionException('message', 3, $previous, ['class' => 'Missing']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['class' => 'Missing'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new ForeignSessionException(context: ['class' => 'Missing', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['class' => 'Replaced', 'session' => 'default']));
        self::assertSame(['class' => 'Replaced', 'kept' => true, 'session' => 'default'], $exception->context);
    }

    #[Test]
    public function it_describes_a_session_that_cannot_be_saved(): void
    {
        $exception = ForeignSessionException::cannotBeSaved("class@anonymous\0/app/src/Session.php:3$0");

        self::assertSame(
            'Unable to save a session of class "class@anonymous\\000/app/src/Session.php:3$0": a session manager saves only the sessions that a session manager creates or loads.',
            $exception->getMessage(),
        );
        self::assertSame(['class' => 'class@anonymous\\000/app/src/Session.php:3$0'], $exception->context);
    }
}
