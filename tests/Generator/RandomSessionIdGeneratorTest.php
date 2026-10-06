<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Generator;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\Generator\RandomSessionIdGenerator;

use function array_unique;

#[CoversClass(RandomSessionIdGenerator::class)]
#[UsesClass(SessionId::class)]
final class RandomSessionIdGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_256_random_bits_as_lowercase_hexadecimal(): void
    {
        $id = new RandomSessionIdGenerator()->generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $id->value);
    }

    #[Test]
    public function it_generates_a_different_id_each_time(): void
    {
        $generator = new RandomSessionIdGenerator();
        $values = [];

        for ($i = 0; $i < 100; $i++) {
            $values[] = $generator->generate()->value;
        }

        self::assertCount(100, array_unique($values));
    }
}
