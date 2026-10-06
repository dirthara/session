<?php

declare(strict_types=1);

namespace Dirthara\Session\ValueObject;

use Dirthara\Session\Exception\InvalidSessionIdException;

use function strlen;
use function preg_match;

final readonly class SessionId
{
    /**
     * @throws InvalidSessionIdException
     */
    public function __construct(
        public string $value,
    ) {
        if (preg_match('/^[A-Za-z0-9_-]{16,256}$/D', $value) !== 1) {
            throw InvalidSessionIdException::malformed(strlen($value));
        }
    }
}
