<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class TestClock implements ClockInterface
{
    public private(set) DateTimeImmutable $now;

    public function __construct(string $now = '2026-10-05 12:00:00.250')
    {
        $this->now = new DateTimeImmutable($now);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
