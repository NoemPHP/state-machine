<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ChainMail;

use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainMail automatically injects dependencies into callable parameters
 */
#[Group('middleware')]
#[Group('chainmail')]
class AutoInjectParametersTest extends TestCase
{
    public function testAutoInjectsSingleParameter(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): \DateTime => new \DateTime('2025-01-01'));

        $injected = null;
        $mail->use(function (\DateTime $dt) use (&$injected) {
            $injected = $dt;
        });

        $mail->boot();

        $this->assertInstanceOf(\DateTime::class, $injected);
    }

    public function testAutoInjectsMultipleParameters(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): string => 'test-string');
        $mail->supply(fn(): int => 123);
        $mail->supply(fn(): array => ['test' => 'array']);

        $str = null;
        $num = null;
        $arr = null;

        $mail->use(function (string $s, int $n, array $a) use (&$str, &$num, &$arr) {
            $str = $s;
            $num = $n;
            $arr = $a;
        });

        $mail->boot();

        $this->assertEquals('test-string', $str);
        $this->assertEquals(123, $num);
        $this->assertEquals(['test' => 'array'], $arr);
    }

    public function testAutoInjectsObjectParameters(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): \stdClass => (object)['value' => 'test']);

        $injected = null;
        $mail->use(function (\stdClass $obj) use (&$injected) {
            $injected = $obj;
        });

        $mail->boot();

        $this->assertInstanceOf(\stdClass::class, $injected);
        $this->assertEquals('test', $injected->value);
    }

    public function testAutoInjectsParametersInDifferentOrder(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): int => 42);
        $mail->supply(fn(): string => 'hello');

        $str = null;
        $num = null;

        // Parameters in different order than supply calls
        $mail->use(function (string $s, int $n) use (&$str, &$num) {
            $str = $s;
            $num = $n;
        });

        $mail->boot();

        $this->assertEquals('hello', $str);
        $this->assertEquals(42, $num);
    }
}
