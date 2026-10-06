<?php

declare(strict_types=1);

namespace Dirthara\Session\ValueObject;

use Dirthara\Session\Exception\InvalidSessionLifetimeException;

final readonly class Lifetime
{
    /**
     * @throws InvalidSessionLifetimeException
     */
    public function __construct(
        public Duration $idle,
        public ?Duration $absolute = null,
    ) {
        $this->validate('idle', $idle);

        if ($absolute !== null) {
            $this->validate('absolute', $absolute);
        }
    }

    /**
     * @throws InvalidSessionLifetimeException
     */
    private function validate(string $lifetime, Duration $duration): void
    {
        if ($duration->milliseconds === 0) {
            throw InvalidSessionLifetimeException::zero($lifetime);
        }
    }
}
