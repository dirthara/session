<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Exception;

use RuntimeException;
use Random\RandomException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\Exception\SessionException;
use Dirthara\Session\Exception\HasExceptionContext;
use Dirthara\Session\Exception\SessionIdGenerationException;

#[CoversClass(SessionIdGenerationException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class SessionIdGenerationExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new SessionIdGenerationException();

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
        $exception = new SessionIdGenerationException('message', 3, $previous, ['length' => 4]);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['length' => 4], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new SessionIdGenerationException(context: ['length' => 4, 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['length' => 5, 'cookie' => 'session']));
        self::assertSame(['length' => 5, 'kept' => true, 'cookie' => 'session'], $exception->context);
    }

    #[Test]
    public function it_describes_a_failing_random_source_and_keeps_the_cause(): void
    {
        $previous = new RandomException('No source of randomness is available.');
        $exception = SessionIdGenerationException::randomSourceFailed($previous);

        self::assertSame(
            'Unable to generate a session ID: the random source failed to provide random bytes.',
            $exception->getMessage(),
        );
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame([], $exception->context);
    }
}
