<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

use Dirthara\Session\Session;
use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\Exception\ForeignSessionException;
use Dirthara\Session\Exception\SessionIdGenerationException;
use Dirthara\Session\Exception\SessionSerialisationException;

interface SessionManager
{
    /**
     * @throws SessionIdGenerationException
     */
    public function create(): Session;

    public function load(SessionId $id): ?Session;

    /**
     * @throws ForeignSessionException
     * @throws SessionSerialisationException
     */
    public function save(Session $session): bool;

    /**
     * @throws ForeignSessionException
     * @throws SessionIdGenerationException
     */
    public function regenerate(Session $session): void;

    /**
     * @throws ForeignSessionException
     * @throws SessionIdGenerationException
     */
    public function invalidate(Session $session): void;

    public function prune(): int;
}
