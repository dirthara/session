<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\ValueObject\StoredSession;

interface SessionStore
{
    public function read(SessionId $id): ?StoredSession;

    public function write(SessionId $id, StoredSession $session): void;

    public function delete(SessionId $id): void;
}
