<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests;

use stdClass;
use Dirthara\Session\Session;
use PHPUnit\Framework\TestCase;
use Dirthara\Session\SessionState;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Session::class)]
#[CoversClass(SessionState::class)]
#[UsesClass(SessionId::class)]
final class SessionTest extends TestCase
{
    private const string ID = 'sessionid-original-0000000000000';

    #[Test]
    public function it_reads_its_id_from_its_state(): void
    {
        $state = new SessionState(new SessionId(self::ID), storedId: null);
        $session = new Session($state);

        $state->id = new SessionId('sessionid-replaced-0000000000000');

        self::assertSame('sessionid-replaced-0000000000000', $session->id->value);
    }

    #[Test]
    public function it_starts_without_values_by_default(): void
    {
        $state = new SessionState(new SessionId(self::ID), storedId: null);

        self::assertSame([], $state->values);
        self::assertFalse(new Session($state)->has('user'));
    }

    #[Test]
    public function it_has_a_key_even_when_its_value_is_null(): void
    {
        $session = $this->session(['flash' => null]);

        self::assertTrue($session->has('flash'));
        self::assertFalse($session->has('user'));
    }

    #[Test]
    public function it_returns_the_value_under_a_key(): void
    {
        $user = new stdClass();

        self::assertSame($user, $this->session(['user' => $user])->get('user'));
    }

    #[Test]
    public function it_returns_the_default_only_for_a_missing_key(): void
    {
        $session = $this->session(['flash' => null, 'count' => 0]);

        self::assertSame('none', $session->get('user', 'none'));
        self::assertNull($session->get('user'));
        self::assertNull($session->get('flash', 'none'));
        self::assertSame(0, $session->get('count', 5));
    }

    #[Test]
    public function it_puts_and_replaces_a_value_in_its_state(): void
    {
        $state = new SessionState(new SessionId(self::ID), storedId: null);
        $session = new Session($state);

        $session->put('user', 42);
        $session->put('locale', 'en_GB');
        $session->put('user', 43);

        self::assertSame(['user' => 43, 'locale' => 'en_GB'], $state->values);
    }

    #[Test]
    public function it_removes_a_key_and_keeps_the_others(): void
    {
        $state = new SessionState(new SessionId(self::ID), storedId: null, values: ['user' => 42, 'locale' => 'en_GB']);
        $session = new Session($state);

        $session->remove('user');
        $session->remove('missing');

        self::assertSame(['locale' => 'en_GB'], $state->values);
    }

    #[Test]
    public function it_clears_every_value_and_keeps_its_id(): void
    {
        $state = new SessionState(new SessionId(self::ID), storedId: null, values: ['user' => 42, 'locale' => 'en_GB']);
        $session = new Session($state);

        $session->clear();

        self::assertSame([], $state->values);
        self::assertSame(self::ID, $session->id->value);
    }

    #[Test]
    public function it_starts_unchanged(): void
    {
        self::assertFalse(new SessionState(new SessionId(self::ID), storedId: null)->changed);
    }

    /**
     * @return iterable<string, array{callable(Session): void}>
     */
    public static function changes(): iterable
    {
        yield 'a put of the same value' => [static fn(Session $session) => $session->put('user', 42)];
        yield 'a removal of a missing key' => [static fn(Session $session) => $session->remove('missing')];
        yield 'clearing' => [static fn(Session $session) => $session->clear()];
    }

    /**
     * @param callable(Session): void $change
     */
    #[Test]
    #[DataProvider('changes')]
    public function it_marks_its_state_changed_by_a_put_a_removal_or_clearing(callable $change): void
    {
        $state = new SessionState(new SessionId(self::ID), storedId: null, values: ['user' => 42]);

        $change(new Session($state));

        self::assertTrue($state->changed);
    }

    #[Test]
    public function it_leaves_its_state_unchanged_when_it_is_only_read(): void
    {
        $state = new SessionState(new SessionId(self::ID), storedId: null, values: ['user' => 42]);
        $session = new Session($state);

        $session->has('user');
        $session->get('user');

        self::assertFalse($state->changed);
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function session(array $values): Session
    {
        return new Session(new SessionState(new SessionId(self::ID), storedId: null, values: $values));
    }
}
