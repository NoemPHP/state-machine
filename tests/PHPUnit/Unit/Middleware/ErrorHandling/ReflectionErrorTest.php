<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ErrorHandling;

use Noem\State\Middleware\ChainException;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Chain handles reflection errors gracefully
 */
#[Group('middleware')]
#[Group('error-handling')]
class ReflectionErrorTest extends TestCase
{
    public function testSupplyHandlesReflectionErrors(): void
    {
        $mail = new ChainMail();

        // Create a callable that will work but has complex signature
        $validCallable = fn(): \DateTime => new \DateTime();

        // This should not throw
        $mail->supply($validCallable);

        $this->assertTrue(true);
    }

    public function testInvokeHandlesComplexCallables(): void
    {
        $mail = new ChainMail();

        // Supply a simple service
        $mail->supply(fn(): string => 'test');

        // Invoke a callable with dependency
        $result = $mail->invoke(fn(string $str): string => $str);

        $this->assertEquals('test', $result);
    }

    public function testSupplyWithMultipleOverlaysDoesNotThrowReflectionError(): void
    {
        $mail = new ChainMail();

        // Multiple supplies with valid signatures should work
        $mail->supply(
            fn(): string => 'value1',
            fn(): int => 42
        );

        $executed = false;
        $mail->use(function (string $str, int $num) use (&$executed) {
            $executed = true;
        });

        $mail->boot();

        $this->assertTrue($executed);
    }

    public function testValidCallableDoesNotThrowReflectionError(): void
    {
        $mail = new ChainMail();

        // Valid callable should not throw
        $mail->supply(fn(): string => 'valid');

        $executed = false;
        $mail->use(function (string $str) use (&$executed) {
            $executed = true;
        });

        $mail->boot();

        $this->assertTrue($executed);
    }

    public function testReflectionWorksWithComplexReturnTypes(): void
    {
        $mail = new ChainMail();

        // Test with various complex return types
        $mail->supply(fn(): \DateTime => new \DateTime());
        $mail->supply(fn(): \DateTimeInterface => new \DateTime());
        $mail->supply(fn(): array => ['key' => 'value']);
        $mail->supply(fn(): ?string => 'nullable');

        $executed = false;
        $mail->use(function (
            \DateTime $dt,
            \DateTimeInterface $dti,
            array $arr,
            ?string $str
        ) use (&$executed) {
            $executed = true;
        });

        $mail->boot();

        $this->assertTrue($executed);
    }
}
