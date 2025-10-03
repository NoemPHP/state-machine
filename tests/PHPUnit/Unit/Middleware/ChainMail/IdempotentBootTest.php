<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ChainMail;

use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainMail can be booted multiple times safely (idempotent)
 */
#[Group('middleware')]
#[Group('chainmail')]
class IdempotentBootTest extends TestCase
{
    public function testMultipleBootsAreSafe(): void
    {
        $mail = new ChainMail();

        $count = 0;
        $mail->supply(fn(): string => 'value');
        $mail->use(function (string $str) use (&$count) {
            $count++;
        });

        $mail->boot();
        $mail->boot();
        $mail->boot();

        // Boot is idempotent - callbacks should only execute once total
        $this->assertEquals(1, $count);
    }

    public function testIdempotentBoot(): void
    {
        $mail = new ChainMail();

        $executed = [];
        $mail->supply(fn(): int => 42);
        $mail->use(function (int $num) use (&$executed) {
            $executed[] = $num;
        });

        $mail->boot();
        $mail->boot();

        // Boot is idempotent - should only execute once
        $this->assertCount(1, $executed);
        $this->assertEquals([42], $executed);
    }
}
