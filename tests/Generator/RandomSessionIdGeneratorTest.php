<?php

declare(strict_types=1);

namespace Dirthara\Session\Tests\Generator;

use Random\RandomException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Session\ValueObject\SessionId;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Session\Exception\HasExceptionContext;
use Dirthara\Session\Tests\Fixtures\FixedRandomEngine;
use Dirthara\Session\Generator\RandomSessionIdGenerator;
use Dirthara\Session\Tests\Fixtures\FailingRandomEngine;
use Dirthara\Session\Exception\SessionIdGenerationException;

use function str_repeat;
use function array_unique;

#[CoversClass(RandomSessionIdGenerator::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(SessionIdGenerationException::class)]
#[UsesTrait(HasExceptionContext::class)]
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

    #[Test]
    public function it_takes_its_bytes_from_the_engine_it_is_given(): void
    {
        $id = new RandomSessionIdGenerator(new FixedRandomEngine())->generate();

        self::assertSame(str_repeat('ab', times: 32), $id->value);
    }

    #[Test]
    public function it_reports_a_random_source_that_fails(): void
    {
        try {
            new RandomSessionIdGenerator(new FailingRandomEngine())->generate();
            self::fail('A failing random source was not reported.');
        } catch (SessionIdGenerationException $exception) {
            self::assertInstanceOf(RandomException::class, $exception->getPrevious());
            self::assertStringNotContainsString('No source', $exception->getMessage());
        }
    }
}
