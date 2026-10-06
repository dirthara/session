<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

final readonly class PrivateSessionValue
{
    public function __construct(
        // @mago-expect analysis:unused-property Native serialisation reads this private property
        private mixed $value,
    ) {}
}
