<?php

declare(strict_types=1);

namespace Dirthara\Session;

use WeakMap;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Dirthara\Session\ValueObject\Lifetime;
use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Contract\SessionSerialiser;
use Dirthara\Session\Contract\SessionIdGenerator;
use Dirthara\Session\Exception\ForeignSessionException;
use Dirthara\Session\Exception\SessionSerialisationException;
use Dirthara\Session\Contract\SessionManager as SessionManagerContract;

use function sprintf;

final readonly class SessionManager implements SessionManagerContract
{
    /**
     * @var WeakMap<Session, SessionState>
     */
    private WeakMap $states;

    public function __construct(
        private SessionStore $store,
        private SessionIdGenerator $ids,
        private SessionSerialiser $serialiser,
        private ClockInterface $clock,
        private Lifetime $lifetime,
    ) {
        $this->states = new WeakMap();
    }

    public function create(): Session
    {
        return $this->track(new SessionState($this->ids->generate(), storedId: null));
    }

    public function load(SessionId $id): ?Session
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

        return $this->track(new SessionState($id, storedId: $id, values: $values));
    }

    /**
     * @throws ForeignSessionException
     * @throws SessionSerialisationException
     */
    public function save(Session $session): bool
    {
        $state = $this->state($session, 'save');

        if ($state->values === []) {
            return $this->forget($state);
        }

        $stored = new StoredSession($this->serialiser->serialise($state->values), $this->expiry());

        if ($state->storedId !== null && $state->storedId->value === $state->id->value) {
            return $this->store->replace($state->id, $stored);
        }

        if ($state->storedId !== null) {
            if (!$this->store->delete($state->storedId)) {
                return false;
            }

            $state->storedId = null;
        }

        $this->store->write($state->id, $stored);
        $state->storedId = $state->id;

        return true;
    }

    /**
     * @throws ForeignSessionException
     */
    public function regenerate(Session $session): void
    {
        $this->state($session, 'regenerate')->id = $this->ids->generate();
    }

    /**
     * @throws ForeignSessionException
     */
    public function invalidate(Session $session): void
    {
        $state = $this->state($session, 'invalidate');
        $state->values = [];
        $state->id = $this->ids->generate();
    }

    private function forget(SessionState $state): bool
    {
        if ($state->storedId !== null) {
            $this->store->delete($state->storedId);
            $state->storedId = null;
        }

        return false;
    }

    private function track(SessionState $state): Session
    {
        $session = new Session($state);
        $this->states[$session] = $state;

        return $session;
    }

    /**
     * @throws ForeignSessionException
     */
    private function state(Session $session, string $operation): SessionState
    {
        return $this->states[$session] ?? throw ForeignSessionException::unknown($operation);
    }

    private function expiry(): DateTimeImmutable
    {
        $milliseconds = $this->lifetime->idle->milliseconds;

        return $this->clock->now()->modify(sprintf('+%d milliseconds', $milliseconds));
    }
}
