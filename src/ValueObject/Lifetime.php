<?php

declare(strict_types=1);

namespace Dirthara\Session\ValueObject;

use Dirthara\Session\Exception\InvalidSessionLifetimeException;

final readonly class Lifetime
{
    // Browsers cap the lifetime of a cookie at 400 days (RFC 6265bis), so a session cannot usefully outlive one.
    public const int MAXIMUM_MILLISECONDS = 400 * 86_400_000;

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
        if ($duration->milliseconds === 0 || $duration->milliseconds > self::MAXIMUM_MILLISECONDS) {
            throw InvalidSessionLifetimeException::outOfRange(
                $lifetime,
                $duration->milliseconds,
                self::MAXIMUM_MILLISECONDS,
            );
        }
    }
}
