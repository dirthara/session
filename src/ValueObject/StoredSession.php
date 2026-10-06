<?php

declare(strict_types=1);

namespace Dirthara\Session\ValueObject;

use DateTimeImmutable;

final readonly class StoredSession
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        public array $values,
        public DateTimeImmutable $expiresAt,
    ) {}
}
