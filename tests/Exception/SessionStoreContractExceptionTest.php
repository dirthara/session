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
use Dirthara\Session\Exception\SessionStoreContractException;

#[CoversClass(SessionStoreContractException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class SessionStoreContractExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new SessionStoreContractException();

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
        $exception = new SessionStoreContractException('message', 3, $previous, ['expectation' => 'read']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['expectation' => 'read'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new SessionStoreContractException(context: ['expectation' => 'read', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['expectation' => 'write', 'store' => 'memory']));
        self::assertSame(['expectation' => 'write', 'kept' => true, 'store' => 'memory'], $exception->context);
    }

    #[Test]
    public function it_describes_the_expectation_a_store_breaks(): void
    {
        $exception = SessionStoreContractException::broken('delete() returns false for an ID it deleted');

        self::assertSame(
            'The session store breaks its contract: delete() returns false for an ID it deleted.',
            $exception->getMessage(),
        );
        self::assertSame(['expectation' => 'delete() returns false for an ID it deleted'], $exception->context);
    }
}
