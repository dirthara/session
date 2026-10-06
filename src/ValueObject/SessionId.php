<?php

declare(strict_types=1);

namespace Dirthara\Session\ValueObject;

final readonly class SessionId
{
    public function __construct(
        public string $value,
    ) {}
}
