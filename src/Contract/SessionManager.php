<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

use Dirthara\Session\ValueObject\SessionId;

interface SessionManager
{
    public function create(): Session;

    public function load(SessionId $id): Session;

    public function save(Session $session): void;
}
