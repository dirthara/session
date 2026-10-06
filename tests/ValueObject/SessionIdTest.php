<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\ValueObject;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Session\Exception\HasExceptionContext;
use Dirthara\Session\Exception\InvalidSessionIdException;

use function print_r;
use function str_repeat;

#[CoversClass(SessionId::class)]
#[UsesClass(InvalidSessionIdException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class SessionIdTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validValues(): iterable
    {
        yield 'lowercase hexadecimal' => ['9f86d081884c7d659a2feaa0c55ad015'];
        yield 'base64url' => ['n4bQgYhMfWWaL-qgxVrQFaO_TxsrC4Is'];
        yield 'every permitted character' => ['AZaz09-_AZaz09-_AZaz09-_AZaz09-_'];
        yield 'the shortest' => [str_repeat('a', times: 32)];
        yield 'the longest' => [str_repeat('a', times: 256)];
    }

    #[Test]
    #[DataProvider('validValues')]
    public function it_carries_a_valid_value(string $value): void
    {
        self::assertSame($value, new SessionId($value)->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'empty' => [''];
        yield 'one character too short' => [str_repeat('a', times: 31)];
        yield 'one character too long' => [str_repeat('a', times: 257)];
        yield 'a path' => ['../../etc/passwd/aaaaaaaaaaaaaaaa'];
        yield 'too short to be hard to guess' => ['9f86d081884c7d65'];
        yield 'a space' => ['9f86d081884c7d65 9a2feaa0c55ad01'];
        yield 'a cookie separator' => ['9f86d081884c7d65;9a2feaa0c55ad01'];
        yield 'a trailing line feed' => ["9f86d081884c7d659a2feaa0c55ad015\n"];
        yield 'a multibyte character' => ['9f86d081884c7d659a2feaa0c55ad01é'];
    }

    #[Test]
    #[DataProvider('invalidValues')]
    public function it_refuses_a_malformed_value(string $value): void
    {
        $this->expectException(InvalidSessionIdException::class);

        new SessionId($value);
    }

    #[Test]
    #[DataProvider('validValues')]
    public function it_tries_a_valid_value(string $value): void
    {
        self::assertSame($value, SessionId::tryFrom($value)?->value);
    }

    #[Test]
    #[DataProvider('invalidValues')]
    public function it_tries_a_malformed_value_without_throwing(string $value): void
    {
        self::assertNull(SessionId::tryFrom($value));
    }

    #[Test]
    public function it_keeps_a_refused_value_out_of_the_exception(): void
    {
        try {
            new SessionId('s3cr3t-session-id!');
            self::fail('A malformed session ID was accepted.');
        } catch (InvalidSessionIdException $exception) {
            self::assertSame(['length' => 18], $exception->context);
            self::assertStringNotContainsString('s3cr3t', $exception->getMessage());
            self::assertStringNotContainsString('s3cr3t', print_r($exception->context, return: true));
        }
    }
}
