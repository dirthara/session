<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

use Dirthara\Session\ValueObject\SessionData;
use Dirthara\Session\ValueObject\SessionId;

interface SessionStore
{
    public function read(SessionId $id): ?SessionData;

    public function write(SessionId $id, SessionData $data): void;

    public function delete(SessionId $id): void;
}
