<?php

declare(strict_types=1);

namespace Dirthara\Session;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Dirthara\Session\ValueObject\Lifetime;
use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Contract\SessionSerialiser;
use Dirthara\Session\Contract\SessionIdGenerator;
use Dirthara\Session\Exception\ForeignSessionException;
use Dirthara\Session\Contract\Session as SessionContract;
use Dirthara\Session\Exception\SessionSerialisationException;
use Dirthara\Session\Contract\SessionManager as SessionManagerContract;

use function sprintf;

final readonly class SessionManager implements SessionManagerContract
{
    public function __construct(
        private SessionStore $store,
        private SessionIdGenerator $ids,
        private SessionSerialiser $serialiser,
        private ClockInterface $clock,
        private Lifetime $lifetime,
    ) {}

    public function create(): SessionContract
    {
        return new Session($this->ids->generate(), $this->ids);
    }

    public function load(SessionId $id): ?SessionContract
    {
        $stored = $this->store->read($id);

        if ($stored === null) {
            return null;
        }

        if ($stored->expiresAt <= $this->clock->now()) {
            $this->store->delete($id);

            return null;
        }

        try {
            $values = $this->serialiser->deserialise($stored->payload);
        } catch (SessionSerialisationException) {
            return null;
        }

        return new Session($id, $this->ids, $values);
    }

    /**
     * @throws ForeignSessionException
     * @throws SessionSerialisationException
     */
    public function save(SessionContract $session): void
    {
        if (!$session instanceof Session) {
            throw ForeignSessionException::cannotBeSaved($session::class);
        }

        $payload = $this->serialiser->serialise($session->values);

        $this->store->write($session->id, new StoredSession($payload, $this->expiry()));

        foreach ($session->replacedIds as $replacedId) {
            $this->store->delete($replacedId);
        }

        $session->forgetReplacedIds();
    }

    private function expiry(): DateTimeImmutable
    {
        $milliseconds = $this->lifetime->idle->milliseconds;

        return $this->clock->now()->modify(sprintf('+%d milliseconds', $milliseconds));
    }
}
