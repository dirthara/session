<?php

declare(strict_types=1);

namespace Dirthara\Session;

use Dirthara\Session\ValueObject\SessionId;

/**
 * @internal
 */
final class SessionState
{
    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(
        public SessionId $id,
        public ?SessionId $storedId,
        public array $values = [],
        public bool $changed = false,
    ) {}
}
