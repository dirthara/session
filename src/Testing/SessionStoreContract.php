<?php

declare(strict_types=1);

namespace Dirthara\Session\Testing;

use Closure;
use DateTimeZone;
use DateTimeImmutable;
use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Exception\SessionStoreContractException;

final readonly class SessionStoreContract
{
    private const string FIRST_ID = '11111111111111111111111111111111';

    private const string SECOND_ID = '22222222222222222222222222222222';

    private const string THIRD_ID = '33333333333333333333333333333333';

    /**
     * @return array<string, Closure(SessionStore): void>
     */
    public function checks(): array
    {
        return [
            'it has nothing under an ID that was never written' => $this->readsNothingUnwritten(...),
            'it reads the session written under an ID' => $this->readsWhatWasWritten(...),
            'it keeps every byte of a payload' => $this->keepsEveryByte(...),
            'it keeps the sessions under different IDs apart' => $this->keepsIdsApart(...),
            'it writes over the session under an ID' => $this->writesOver(...),
            'it replaces a session only under an ID it has' => $this->replacesOnlyExisting(...),
            'it does not replace a session it deleted' => $this->doesNotReplaceDeleted(...),
            'it touches only the expiry of a session under an ID it has' => $this->touchesOnlyExpiry(...),
            'it deletes the session under an ID and keeps the others' => $this->deletesOne(...),
            'it reports that it had no session under an ID it deletes' => $this->reportsMissingDelete(...),
        ];
    }

    /**
     * @return array<string, Closure(SessionStore): void>
     */
    public function pruningChecks(): array
    {
        return [
            'it keeps an expired session until it is pruned' => $this->keepsExpired(...),
            'it prunes the sessions that expire by the given moment and counts them' => $this->prunesExpired(...),
            'it prunes by the expiry a touch gave a session' => $this->prunesByTouchedExpiry(...),
        ];
    }

    /**
     * @throws SessionStoreContractException
     */
    private function readsNothingUnwritten(SessionStore $store): void
    {
        $this->expect($store->read($this->id(self::FIRST_ID)) === null, 'read() returns null for an ID never written');
    }

    /**
     * @throws SessionStoreContractException
     */
    private function readsWhatWasWritten(SessionStore $store): void
    {
        $session = $this->stored('user 1');

        $store->write($this->id(self::FIRST_ID), $session);

        $this->expectStored($session, $store->read($this->id(self::FIRST_ID)), 'read() returns what write() stored');
    }

    /**
     * @throws SessionStoreContractException
     */
    private function keepsEveryByte(SessionStore $store): void
    {
        $session = $this->stored("a:1:{s:4:\"name\";s:6:\"\0\xFF\xE9\n\r\t\";}");

        $store->write($this->id(self::FIRST_ID), $session);

        $this->expectStored(
            $session,
            $store->read($this->id(self::FIRST_ID)),
            'read() returns a payload with null bytes, invalid UTF-8, and control characters unchanged',
        );
    }

    /**
     * @throws SessionStoreContractException
     */
    private function keepsIdsApart(SessionStore $store): void
    {
        $first = $this->stored('user 1');
        $second = $this->stored('user 2');

        $store->write($this->id(self::FIRST_ID), $first);
        $store->write($this->id(self::SECOND_ID), $second);

        $this->expectStored($first, $store->read($this->id(self::FIRST_ID)), 'write() to one ID leaves another alone');
        $this->expectStored(
            $second,
            $store->read($this->id(self::SECOND_ID)),
            'read() returns each ID its own session',
        );
    }

    /**
     * @throws SessionStoreContractException
     */
    private function writesOver(SessionStore $store): void
    {
        $replacement = $this->stored('user 2');
        $store->write($this->id(self::FIRST_ID), $this->stored('user 1'));

        $store->write($this->id(self::FIRST_ID), $replacement);

        $this->expectStored(
            $replacement,
            $store->read($this->id(self::FIRST_ID)),
            'write() replaces a session already under the ID',
        );
    }

    /**
     * @throws SessionStoreContractException
     */
    private function replacesOnlyExisting(SessionStore $store): void
    {
        $replacement = $this->stored('user 2');
        $store->write($this->id(self::FIRST_ID), $this->stored('user 1'));

        $this->expect(
            $store->replace($this->id(self::FIRST_ID), $replacement),
            'replace() returns true for an ID it has',
        );
        $this->expect(
            !$store->replace($this->id(self::SECOND_ID), $this->stored('user 3')),
            'replace() returns false for an ID it does not have',
        );
        $this->expectStored(
            $replacement,
            $store->read($this->id(self::FIRST_ID)),
            'replace() stores the session under an ID it has',
        );
        $this->expect(
            $store->read($this->id(self::SECOND_ID)) === null,
            'replace() stores nothing under an ID it does not have',
        );
    }

    /**
     * @throws SessionStoreContractException
     */
    private function doesNotReplaceDeleted(SessionStore $store): void
    {
        $store->write($this->id(self::FIRST_ID), $this->stored('user 1'));
        $store->delete($this->id(self::FIRST_ID));

        $this->expect(
            !$store->replace($this->id(self::FIRST_ID), $this->stored('user 2')),
            'replace() returns false for an ID it deleted',
        );
        $this->expect(
            $store->read($this->id(self::FIRST_ID)) === null,
            'replace() does not bring back a session it deleted',
        );
    }

    /**
     * @throws SessionStoreContractException
     */
    private function touchesOnlyExpiry(SessionStore $store): void
    {
        $later = $this->moment('2100-01-01 16:00:00.500');
        $store->write($this->id(self::FIRST_ID), $this->stored('user 1'));

        $this->expect($store->touch($this->id(self::FIRST_ID), $later), 'touch() returns true for an ID it has');
        $this->expect(
            !$store->touch($this->id(self::SECOND_ID), $later),
            'touch() returns false for an ID it does not have',
        );
        $this->expectStored(
            new StoredSession('user 1', $this->moment('2100-01-01 10:00:00.125'), $later),
            $store->read($this->id(self::FIRST_ID)),
            'touch() changes the expiry and keeps the payload and the creation moment',
        );
        $this->expect(
            $store->read($this->id(self::SECOND_ID)) === null,
            'touch() stores nothing under an ID it does not have',
        );
    }

    /**
     * @throws SessionStoreContractException
     */
    private function keepsExpired(SessionStore $store): void
    {
        $expired = new StoredSession(
            'user 1',
            $this->moment('2000-01-01 00:00:00'),
            $this->moment('2000-01-01 02:00:00'),
        );

        $store->write($this->id(self::FIRST_ID), $expired);

        $this->expectStored(
            $expired,
            $store->read($this->id(self::FIRST_ID)),
            'read() returns an expired session, because expiry is up to the manager',
        );
    }

    /**
     * @throws SessionStoreContractException
     */
    private function deletesOne(SessionStore $store): void
    {
        $kept = $this->stored('user 2');
        $store->write($this->id(self::FIRST_ID), $this->stored('user 1'));
        $store->write($this->id(self::SECOND_ID), $kept);

        $this->expect($store->delete($this->id(self::FIRST_ID)), 'delete() returns true for an ID it has');
        $this->expect($store->read($this->id(self::FIRST_ID)) === null, 'delete() removes the session under the ID');
        $this->expectStored(
            $kept,
            $store->read($this->id(self::SECOND_ID)),
            'delete() keeps the sessions under other IDs',
        );
    }

    /**
     * @throws SessionStoreContractException
     */
    private function reportsMissingDelete(SessionStore $store): void
    {
        $store->write($this->id(self::FIRST_ID), $this->stored('user 1'));
        $store->delete($this->id(self::FIRST_ID));

        $this->expect(!$store->delete($this->id(self::FIRST_ID)), 'delete() returns false for an ID it deleted');
        $this->expect(!$store->delete($this->id(self::SECOND_ID)), 'delete() returns false for an ID never written');
    }

    /**
     * @throws SessionStoreContractException
     */
    private function prunesExpired(SessionStore $store): void
    {
        $createdAt = $this->moment('2026-10-05 10:00:00');
        $store->write(
            $this->id(self::FIRST_ID),
            new StoredSession('a', $createdAt, $this->moment('2026-10-05 12:00:00')),
        );
        $store->write(
            $this->id(self::SECOND_ID),
            new StoredSession('b', $createdAt, $this->moment('2026-10-05 13:00:00')),
        );
        $store->write(
            $this->id(self::THIRD_ID),
            new StoredSession('c', $createdAt, $this->moment('2026-10-05 12:00:00.001')),
        );

        $this->expect(
            $store->prune($this->moment('2026-10-05 12:00:00')) === 1,
            'prune() removes and counts the sessions that expire at or before the moment',
        );
        $this->expect(
            $store->read($this->id(self::FIRST_ID)) === null,
            'prune() removes a session that expires at the moment',
        );
        $this->expect(
            $store->read($this->id(self::SECOND_ID)) !== null && $store->read($this->id(self::THIRD_ID)) !== null,
            'prune() keeps the sessions that expire after the moment, even by a millisecond',
        );
        $this->expect(
            $store->prune($this->moment('2026-10-05 13:00:00')) === 2,
            'prune() counts every session it removes',
        );
        $this->expect(
            $store->prune($this->moment('2026-10-05 13:00:00')) === 0,
            'prune() returns 0 when nothing has expired',
        );
    }

    /**
     * @throws SessionStoreContractException
     */
    private function prunesByTouchedExpiry(SessionStore $store): void
    {
        $store->write($this->id(self::FIRST_ID), $this->stored('user 1'));
        $store->touch($this->id(self::FIRST_ID), $this->moment('2100-01-01 18:00:00'));

        $this->expect(
            $store->prune($this->moment('2100-01-01 17:00:00')) === 0
            && $store->read($this->id(self::FIRST_ID)) !== null,
            'prune() uses the expiry that touch() gave a session',
        );
    }

    private function id(string $value): SessionId
    {
        return new SessionId($value);
    }

    private function stored(string $payload): StoredSession
    {
        return new StoredSession(
            $payload,
            $this->moment('2100-01-01 10:00:00.125'),
            $this->moment('2100-01-01 14:00:00.375'),
        );
    }

    private function moment(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }

    /**
     * @throws SessionStoreContractException
     */
    private function expect(bool $kept, string $expectation): void
    {
        if (!$kept) {
            throw SessionStoreContractException::broken($expectation);
        }
    }

    /**
     * @throws SessionStoreContractException
     */
    private function expectStored(StoredSession $expected, ?StoredSession $actual, string $expectation): void
    {
        $this->expect(
            $actual !== null
            && $actual->payload === $expected->payload
            && $this->utc($actual->createdAt) === $this->utc($expected->createdAt)
            && $this->utc($actual->expiresAt) === $this->utc($expected->expiresAt),
            $expectation,
        );
    }

    private function utc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }
}
