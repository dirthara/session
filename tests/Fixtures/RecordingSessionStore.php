<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Fixtures;

use Dirthara\Session\Contract\SessionStore;
use Dirthara\Session\ValueObject\SessionId;
use Dirthara\Session\ValueObject\StoredSession;
use Dirthara\Session\Driver\Memory\MemorySessionStore;

use function in_array;

final class RecordingSessionStore implements SessionStore
{
    /**
     * @var list<string>
     */
    public private(set) array $calls = [];

    /**
     * @var list<string>
     */
    public array $throwingOperations = [];

    public function __construct(
        public readonly MemorySessionStore $inner = new MemorySessionStore(),
    ) {}

    public function read(SessionId $id): ?StoredSession
    {
        $this->record('read', $id);

        return $this->inner->read($id);
    }

    public function write(SessionId $id, StoredSession $session): void
    {
        $this->record('write', $id);

        $this->inner->write($id, $session);
    }

    public function replace(SessionId $id, StoredSession $session): bool
    {
        $this->record('replace', $id);

        return $this->inner->replace($id, $session);
    }

    public function delete(SessionId $id): bool
    {
        $this->record('delete', $id);

        return $this->inner->delete($id);
    }

    private function record(string $operation, SessionId $id): void
    {
        $this->calls[] = $operation . ' ' . $id->value;

        if (in_array($operation, $this->throwingOperations, strict: true)) {
            throw new ContextualException('The store is unavailable.');
        }
    }
}
