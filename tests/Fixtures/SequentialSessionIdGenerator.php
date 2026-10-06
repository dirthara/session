<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\Contract\SessionIdGenerator;

use function sprintf;

final class SequentialSessionIdGenerator implements SessionIdGenerator
{
    public private(set) int $generated = 0;

    public function generate(): SessionId
    {
        $this->generated++;

        return new SessionId(sprintf('sessionid-%022d', $this->generated));
    }
}
