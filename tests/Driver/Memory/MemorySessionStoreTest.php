<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Driver\Memory;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Testing\SessionStoreContract;
use Dirthara\Session\Driver\Memory\MemorySessionStore;

#[CoversClass(MemorySessionStore::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(StoredSession::class)]
#[UsesClass(SessionStoreContract::class)]
final class MemorySessionStoreTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(SessionStore): void}>
     */
    public static function storeContract(): iterable
    {
        $contract = new SessionStoreContract();

        foreach ([...$contract->checks(), ...$contract->pruningChecks()] as $name => $check) {
            yield $name => [$check];
        }
    }

    /**
     * @param Closure(SessionStore): void $check
     */
    #[Test]
    #[DataProvider('storeContract')]
    public function it_keeps_the_store_contract(Closure $check): void
    {
        $this->expectNotToPerformAssertions();

        $check(new MemorySessionStore());
    }

    #[Test]
    public function it_returns_the_session_object_it_was_given(): void
    {
        $store = new MemorySessionStore();
        $session = new StoredSession(
            'user 1',
            new DateTimeImmutable('2026-10-05 10:00:00'),
            new DateTimeImmutable('2026-10-05 14:00:00'),
        );

        $store->write(new SessionId('11111111111111111111111111111111'), $session);

        self::assertSame($session, $store->read(new SessionId('11111111111111111111111111111111')));
    }
}
