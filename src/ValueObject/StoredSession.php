<?php

declare(strict_types=1);

namespace Dirthara\Session\ValueObject;

use DateTimeImmutable;

final readonly class StoredSession
{
    public function __construct(
        public string $payload,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
    ) {}
}
