<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\Exception\SessionIdGenerationException;

interface SessionIdGenerator
{
    /**
     * @throws SessionIdGenerationException
     */
    public function generate(): SessionId;
}
