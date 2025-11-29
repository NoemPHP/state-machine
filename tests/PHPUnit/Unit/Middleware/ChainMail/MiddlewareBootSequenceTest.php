<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ChainMail;

use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainMail supports middleware boot sequence through use() method
 */
#[Group('middleware')]
#[Group('chainmail')]
class MiddlewareBootSequenceTest extends TestCase
{
    public function testBootSequenceExecutesUseCallbacks(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): string => 'value');

        $executed = false;
        $mail->use(function (string $str) use (&$executed) {
            $executed = true;
        });

        $this->assertFalse($executed, 'Should not execute before boot');

        $mail->boot();

        $this->assertTrue($executed, 'Should execute after boot');
    }

    public function testMultipleUseCallbacksExecuteInOrder(): void
    {
        $mail = new ChainMail();
        $order = [];

        $mail->supply(fn(): string => 'value');

        $mail->use(function (string $str) use (&$order) {
            $order[] = 'first';
        });

        $mail->use(function (string $str) use (&$order) {
            $order[] = 'second';
        });

        $mail->use(function (string $str) use (&$order) {
            $order[] = 'third';
        });

        $mail->boot();

        // Boot sequence executes in LIFO order (last registered, first executed)
        // This allows features registered later (during build) to wrap earlier features
        $this->assertEquals(['third', 'second', 'first'], $order);
    }

    public function testUseMethodReturnsChainMail(): void
    {
        $mail = new ChainMail();

        $result = $mail->use(fn() => null);

        $this->assertInstanceOf(ChainMail::class, $result);
        $this->assertSame($mail, $result);
    }
}
