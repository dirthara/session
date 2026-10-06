<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\ValueObject;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\ValueObject\StoredSession;

#[CoversClass(StoredSession::class)]
final class StoredSessionTest extends TestCase
{
    #[Test]
    public function it_carries_the_payload_and_the_moment_it_expires(): void
    {
        $expiresAt = new DateTimeImmutable('2026-10-05 14:00:00');
        $session = new StoredSession('a:1:{s:4:"user";i:42;}', $expiresAt);

        self::assertSame('a:1:{s:4:"user";i:42;}', $session->payload);
        self::assertSame($expiresAt, $session->expiresAt);
    }
}
