<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

final class Customer
{
    public function __construct(
        public string $name,
        public Address $address,
    ) {}
}
