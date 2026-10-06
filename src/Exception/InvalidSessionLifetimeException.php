<?php

declare(strict_types=1);

namespace Dirthara\Session\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;

final class InvalidSessionLifetimeException extends InvalidArgumentException implements SessionException
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

    public static function zero(string $lifetime): self
    {
        return new self(
            message: sprintf(
                'Unable to use an %s session lifetime of zero: a session has to live for longer than that.',
                $lifetime,
            ),
            context: ['lifetime' => $lifetime],
        );
    }
}
