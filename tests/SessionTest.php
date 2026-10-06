<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests;

use stdClass;
use Dirthara\Session\Session;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\Tests\Fixtures\SequentialSessionIdGenerator;

#[CoversClass(Session::class)]
#[UsesClass(SessionId::class)]
final class SessionTest extends TestCase
{
    private const string ID = 'sessionid-original-0000000000000';

    #[Test]
    public function it_carries_its_id_and_values(): void
    {
        $session = $this->session(['user' => 42]);

        self::assertSame(self::ID, $session->id->value);
        self::assertSame(['user' => 42], $session->values);
        self::assertSame([], $session->replacedIds);
    }

    #[Test]
    public function it_starts_without_values_by_default(): void
    {
        $session = new Session(new SessionId(self::ID), new SequentialSessionIdGenerator());

        self::assertSame([], $session->values);
        self::assertFalse($session->has('user'));
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
        $session = $this->session(['user' => $user]);

        self::assertSame($user, $session->get('user'));
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
    public function it_puts_and_replaces_a_value(): void
    {
        $session = $this->session();

        $session->put('user', 42);
        $session->put('locale', 'en_GB');
        $session->put('user', 43);

        self::assertSame(['user' => 43, 'locale' => 'en_GB'], $session->values);
    }

    #[Test]
    public function it_removes_a_key_and_keeps_the_others(): void
    {
        $session = $this->session(['user' => 42, 'locale' => 'en_GB']);

        $session->remove('user');
        $session->remove('missing');

        self::assertSame(['locale' => 'en_GB'], $session->values);
    }

    #[Test]
    public function it_clears_every_value_and_keeps_its_id(): void
    {
        $session = $this->session(['user' => 42, 'locale' => 'en_GB']);

        $session->clear();

        self::assertSame([], $session->values);
        self::assertSame(self::ID, $session->id->value);
        self::assertSame([], $session->replacedIds);
    }

    #[Test]
    public function it_regenerates_its_id_and_keeps_its_values(): void
    {
        $session = $this->session(['user' => 42]);
        $original = $session->id;

        $session->regenerate();

        self::assertSame('sessionid-0000000000000000000001', $session->id->value);
        self::assertSame([$original], $session->replacedIds);
        self::assertSame(['user' => 42], $session->values);
    }

    #[Test]
    public function it_remembers_every_id_it_replaced(): void
    {
        $session = $this->session();

        $session->regenerate();
        $session->regenerate();

        self::assertSame('sessionid-0000000000000000000002', $session->id->value);
        self::assertSame([self::ID, 'sessionid-0000000000000000000001'], [
            $session->replacedIds[0]->value,
            $session->replacedIds[1]->value,
        ]);
    }

    #[Test]
    public function it_invalidates_by_clearing_its_values_and_regenerating_its_id(): void
    {
        $session = $this->session(['user' => 42]);
        $original = $session->id;

        $session->invalidate();

        self::assertSame([], $session->values);
        self::assertSame('sessionid-0000000000000000000001', $session->id->value);
        self::assertSame([$original], $session->replacedIds);
    }

    #[Test]
    public function it_forgets_the_ids_it_replaced_and_keeps_its_current_id(): void
    {
        $session = $this->session();
        $session->regenerate();

        $session->forgetReplacedIds();

        self::assertSame([], $session->replacedIds);
        self::assertSame('sessionid-0000000000000000000001', $session->id->value);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function session(array $values = []): Session
    {
        return new Session(new SessionId(self::ID), new SequentialSessionIdGenerator(), $values);
    }
}
