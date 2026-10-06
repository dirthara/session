<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Testing;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Testing\SessionStoreContract;
use Dirthara\Session\Exception\HasExceptionContext;
use Dirthara\Session\Driver\Memory\MemorySessionStore;
use Dirthara\Session\Tests\Fixtures\FlawedSessionStore;
use Dirthara\Session\Exception\SessionStoreContractException;

use function array_keys;

#[CoversClass(SessionStoreContract::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(StoredSession::class)]
#[UsesClass(MemorySessionStore::class)]
#[UsesClass(SessionStoreContractException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class SessionStoreContractTest extends TestCase
{
    #[Test]
    public function it_names_every_check(): void
    {
        self::assertSame(
            [
                'it has nothing under an ID that was never written',
                'it reads the session written under an ID',
                'it keeps every byte of a payload',
                'it keeps the sessions under different IDs apart',
                'it writes over the session under an ID',
                'it replaces a session only under an ID it has',
                'it does not replace a session it deleted',
                'it touches only the expiry of a session under an ID it has',
                'it keeps an expired session until it is pruned',
                'it deletes the session under an ID and keeps the others',
                'it reports that it had no session under an ID it deletes',
                'it prunes the sessions that expire by the given moment and counts them',
                'it prunes by the expiry a touch gave a session',
            ],
            array_keys(new SessionStoreContract()->checks()),
        );
    }

    #[Test]
    public function it_passes_a_store_that_keeps_the_contract(): void
    {
        $this->expectNotToPerformAssertions();

        foreach (new SessionStoreContract()->checks() as $check) {
            $check(new MemorySessionStore());
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function flaws(): iterable
    {
        yield 'a truncated payload' => [
            'truncates payloads',
            'it keeps every byte of a payload',
            'read() returns a payload with null bytes, invalid UTF-8, and control characters unchanged',
        ];
        yield 'a lost time of day' => [
            'drops seconds',
            'it reads the session written under an ID',
            'read() returns what write() stored',
        ];
        yield 'a replace that creates' => [
            'replaces what it does not have',
            'it replaces a session only under an ID it has',
            'replace() returns false for an ID it does not have',
        ];
        yield 'a replace that brings back a deleted session' => [
            'replaces what it does not have',
            'it does not replace a session it deleted',
            'replace() returns false for an ID it deleted',
        ];
        yield 'a touch that rewrites the creation moment' => [
            'touch rewrites the creation moment',
            'it touches only the expiry of a session under an ID it has',
            'touch() changes the expiry and keeps the payload and the creation moment',
        ];
        yield 'a delete that always succeeds' => [
            'delete always succeeds',
            'it reports that it had no session under an ID it deletes',
            'delete() returns false for an ID it deleted',
        ];
        yield 'a prune that does nothing' => [
            'never prunes',
            'it prunes the sessions that expire by the given moment and counts them',
            'prune() removes and counts the sessions that expire at or before the moment',
        ];
    }

    #[Test]
    #[DataProvider('flaws')]
    public function it_reports_the_expectation_a_flawed_store_breaks(
        string $flaw,
        string $check,
        string $expectation,
    ): void {
        $this->expectExceptionObject(SessionStoreContractException::broken($expectation));

        new SessionStoreContract()->checks()[$check](new FlawedSessionStore($flaw));
    }
}
