<?php

declare(strict_types=1);

namespace Dirthara\Session\Exception;

use Throwable;
use RuntimeException;

final class SessionIdGenerationException extends RuntimeException implements SessionException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    public static function randomSourceFailed(?Throwable $previous = null): self
    {
        return new self(
            message: 'Unable to generate a session ID: the random source failed to provide random bytes.',
            previous: $previous,
        );
    }
}
