<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

final class Address
{
    public function __construct(
        public string $city,
    ) {}
}
