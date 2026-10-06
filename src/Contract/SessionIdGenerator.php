<?php

declare(strict_types=1);

namespace Dirthara\Session\Contract;

use Dirthara\Session\ValueObject\SessionId;

interface SessionIdGenerator
{
    public function generate(): SessionId;
}
