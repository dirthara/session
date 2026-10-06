<?php

declare(strict_types=1);

namespace Dirthara\Session\Generator;

use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\Contract\SessionIdGenerator;

use function bin2hex;
use function random_bytes;

final readonly class RandomSessionIdGenerator implements SessionIdGenerator
{
    public function generate(): SessionId
    {
        return new SessionId(bin2hex(random_bytes(32)));
    }
}
