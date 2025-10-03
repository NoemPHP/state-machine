<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ChainMail;

use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Overlay;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainMail supports overlay pattern with #[Overlay] attribute for decorating services
 */
#[Group('middleware')]
#[Group('chainmail')]
class OverlayPatternTest extends TestCase
{
    public function testOverlayDecoratesService(): void
    {
        $mail = new ChainMail();

        $mail->supply(
            fn(): \DateTimeImmutable => new \DateTimeImmutable('2025-01-01'),
            #[Overlay] function (callable $next): \DateTimeImmutable {
                $date = $next();
                return $date->modify('+1 day');
            }
        );

        $received = null;
        $mail->use(function (\DateTimeImmutable $dt) use (&$received) {
            $received = $dt;
        });

        $mail->boot();

        $this->assertEquals('2025-01-02', $received->format('Y-m-d'));
    }

    public function testMultipleOverlaysChain(): void
    {
        $mail = new ChainMail();

        $mail->supply(
            fn(): int => 10,
            #[Overlay] fn(callable $next): int => $next() + 5,
            #[Overlay] fn(callable $next): int => $next() * 2
        );

        $received = null;
        $mail->use(function (int $value) use (&$received) {
            $received = $value;
        });

        $mail->boot();

        // First overlay gets base (10) + 5 = 15, then passes to second overlay
        // Second overlay gets 15 * 2... wait no
        // Actually: first overlay wraps second which wraps base
        // So execution: first calls next -> second calls next -> base returns 10
        // Second returns: 10 * 2 = 20
        // First returns: 20 + 5 = 25
        $this->assertEquals(25, $received);
    }

    public function testOverlayCanAccessOriginalValue(): void
    {
        $mail = new ChainMail();

        $mail->supply(
            fn(): string => 'original',
            #[Overlay] function (callable $next): string {
                $original = $next();
                $this->assertEquals('original', $original);
                return strtoupper($original);
            }
        );

        $received = null;
        $mail->use(function (string $str) use (&$received) {
            $received = $str;
        });

        $mail->boot();

        $this->assertEquals('ORIGINAL', $received);
    }

    public function testOverlayWithComplexTransformation(): void
    {
        $mail = new ChainMail();

        $mail->supply(
            fn(): array => ['items' => [1, 2, 3]],
            #[Overlay] function (callable $next): array {
                $data = $next();
                $data['items'] = array_map(fn($x) => $x * 2, $data['items']);
                return $data;
            }
        );

        $received = null;
        $mail->use(function (array $arr) use (&$received) {
            $received = $arr;
        });

        $mail->boot();

        $this->assertEquals(['items' => [2, 4, 6]], $received);
    }
}
