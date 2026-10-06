<?php

declare(strict_types=1);

namespace Dirthara\Session;

use WeakMap;
use DateTimeZone;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Dirthara\Session\ValueObject\Duration;
use Dirthara\Session\ValueObject\Lifetime;
use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Contract\SessionSerialiser;
use Dirthara\Session\Contract\SessionIdGenerator;
use Dirthara\Session\Exception\ForeignSessionException;
use Dirthara\Session\Exception\SessionSerialisationException;
use Dirthara\Session\Contract\SessionManager as SessionManagerContract;

use function min;
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

        if ($stored->expiresAt <= $this->now()) {
            return null;
        }

        try {
            $values = $this->serialiser->deserialise($stored->payload);
        } catch (SessionSerialisationException) {
            return null;
        }

        return $this->track(
            new SessionState(
                $id,
                storedId: $id,
                values: $values,
                createdAt: $this->utc($stored->createdAt),
                expiresAt: $this->utc($stored->expiresAt),
                payload: $stored->payload,
            ),
        );
    }

    /**
     * @throws ForeignSessionException
     * @throws SessionSerialisationException
     */
    public function save(Session $session): bool
    {
        $state = $this->state($session, 'save');
        $now = $this->now();

        if ($this->expired($state, $now)) {
            return false;
        }

        if ($state->values === []) {
            return $this->forget($state);
        }

        $createdAt = $state->createdAt ?? $now;
        $expiresAt = $this->expiry($now, $createdAt);

        if ($expiresAt <= $now) {
            return $this->forget($state);
        }

        if ($state->storedId !== null && $state->storedId->value === $state->id->value) {
            return $this->update($state, $createdAt, $expiresAt);
        }

        return $this->move($state, $createdAt, $expiresAt);
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
        $state->createdAt = null;
        $state->id = $this->ids->generate();
    }

    /**
     * @throws SessionSerialisationException
     */
    public function prune(): int
    {
        return $this->store->prune($this->now());
    }

    /**
     * @throws SessionSerialisationException
     */
    private function update(SessionState $state, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt): bool
    {
        $payload = $this->serialiser->serialise($state->values);
        $updated = $payload === $state->payload
            ? $this->store->touch($state->id, $expiresAt)
            : $this->store->replace($state->id, new StoredSession($payload, $createdAt, $expiresAt));

        if ($updated) {
            $state->expiresAt = $expiresAt;
            $state->payload = $payload;
        }

        return $updated;
    }

    /**
     * @throws SessionSerialisationException
     */
    private function move(SessionState $state, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt): bool
    {
        $payload = $this->serialiser->serialise($state->values);

        if ($state->storedId !== null && !$this->store->delete($state->storedId)) {
            return false;
        }

        $state->storedId = null;
        $this->store->write($state->id, new StoredSession($payload, $createdAt, $expiresAt));
        $state->storedId = $state->id;
        $state->createdAt = $createdAt;
        $state->expiresAt = $expiresAt;
        $state->payload = $payload;

        return true;
    }

    private function forget(SessionState $state): bool
    {
        if ($state->storedId !== null && $this->store->delete($state->storedId)) {
            $state->storedId = null;
            $state->expiresAt = null;
            $state->payload = null;
        }

        return false;
    }

    private function expired(SessionState $state, DateTimeImmutable $now): bool
    {
        return $state->storedId !== null && $state->expiresAt !== null && $state->expiresAt <= $now;
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

    private function expiry(DateTimeImmutable $now, DateTimeImmutable $createdAt): DateTimeImmutable
    {
        $idle = $this->later($now, $this->lifetime->idle);

        if ($this->lifetime->absolute === null) {
            return $idle;
        }

        return min($idle, $this->later($createdAt, $this->lifetime->absolute));
    }

    private function now(): DateTimeImmutable
    {
        return $this->utc($this->clock->now());
    }

    private function utc(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone(new DateTimeZone('UTC'));
    }

    private function later(DateTimeImmutable $moment, Duration $duration): DateTimeImmutable
    {
        return $moment->modify(sprintf('+%d milliseconds', $duration->milliseconds));
    }
}
