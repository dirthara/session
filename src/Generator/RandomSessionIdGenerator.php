<?php

declare(strict_types=1);

namespace Dirthara\Session\Generator;

use Random\Engine;
use Random\Randomizer;
use Random\Engine\Secure;
use Random\RandomException;
use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\Contract\SessionIdGenerator;
use Dirthara\Session\Exception\SessionIdGenerationException;

use function bin2hex;

final readonly class RandomSessionIdGenerator implements SessionIdGenerator
{
    public function __construct(
        private Engine $engine = new Secure(),
    ) {}

    /**
     * @throws SessionIdGenerationException
     */
    public function generate(): SessionId
    {
        try {
            $bytes = new Randomizer($this->engine)->getBytes(32);
        } catch (RandomException $exception) {
            throw SessionIdGenerationException::randomSourceFailed($exception);
        }

        return new SessionId(bin2hex($bytes));
    }
}
