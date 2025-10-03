<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ChainMail;

use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainMail automatically resolves dependencies based on parameter types
 */
#[Group('middleware')]
#[Group('chainmail')]
class AutoResolveDependenciesTest extends TestCase
{
    public function testAutoResolvesByParameterType(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): \DateTime => new \DateTime('2025-01-01'));

        $resolved = false;
        $mail->use(function (\DateTime $dt) use (&$resolved) {
            $resolved = true;
            $this->assertEquals('2025-01-01', $dt->format('Y-m-d'));
        });

        $mail->boot();

        $this->assertTrue($resolved);
    }

    public function testResolvesMultipleParameterTypes(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): string => 'string-value');
        $mail->supply(fn(): int => 42);
        $mail->supply(fn(): bool => true);

        $receivedString = null;
        $receivedInt = null;
        $receivedBool = null;

        $mail->use(function (string $str, int $num, bool $flag) use (&$receivedString, &$receivedInt, &$receivedBool) {
            $receivedString = $str;
            $receivedInt = $num;
            $receivedBool = $flag;
        });

        $mail->boot();

        $this->assertEquals('string-value', $receivedString);
        $this->assertEquals(42, $receivedInt);
        $this->assertTrue($receivedBool);
    }

    public function testResolvesNestedDependencies(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): string => 'base');
        $mail->supply(fn(string $s): int => strlen($s));
        $mail->supply(fn(int $i): array => ['length' => $i]);

        $received = null;
        $mail->use(function (array $arr) use (&$received) {
            $received = $arr;
        });

        $mail->boot();

        $this->assertEquals(['length' => 4], $received);
    }

    public function testAutoResolvesBasedOnClassName(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): \DateTimeImmutable => new \DateTimeImmutable('2025-01-15'));

        $received = null;
        $mail->use(function (\DateTimeImmutable $dt) use (&$received) {
            $received = $dt;
        });

        $mail->boot();

        $this->assertInstanceOf(\DateTimeImmutable::class, $received);
        $this->assertEquals('2025-01-15', $received->format('Y-m-d'));
    }
}
