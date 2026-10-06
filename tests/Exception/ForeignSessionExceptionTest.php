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
        $exception = new ForeignSessionException('message', 3, $previous, ['operation' => 'Missing']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['operation' => 'Missing'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new ForeignSessionException(context: ['operation' => 'Missing', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['operation' => 'Replaced', 'session' => 'default']));
        self::assertSame(['operation' => 'Replaced', 'kept' => true, 'session' => 'default'], $exception->context);
    }

    #[Test]
    public function it_describes_a_session_the_manager_did_not_create_or_load(): void
    {
        $exception = ForeignSessionException::unknown('save
forged');

        self::assertSame(
            'Unable to save\nforged the session: this session manager did not create or load it.',
            $exception->getMessage(),
        );
        self::assertSame(['operation' => 'save\nforged'], $exception->context);
    }
}
