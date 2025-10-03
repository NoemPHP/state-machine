<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ChainMail;

use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * Acceptance Criterion: ChainMail validates that factory callables have return type declarations
 */
#[Group('middleware')]
#[Group('chainmail')]
class ReturnTypeValidationTest extends TestCase
{
    public function testRequiresReturnTypeDeclaration(): void
    {
        $mail = new ChainMail();

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Dependency providers MUST specify a return type');

        // Factory without return type - should throw
        $mail->supply(function () {
            return 'value';
        });
    }

    public function testAcceptsFactoryWithReturnType(): void
    {
        $mail = new ChainMail();

        // This should not throw
        $mail->supply(fn(): string => 'value');

        $received = null;
        $mail->use(function (string $str) use (&$received) {
            $received = $str;
        });

        $mail->boot();

        $this->assertEquals('value', $received);
    }

    public function testAcceptsComplexReturnTypes(): void
    {
        $mail = new ChainMail();

        // Should accept object return types
        $mail->supply(fn(): \DateTime => new \DateTime());

        // Should accept array return type
        $mail->supply(fn(): array => ['key' => 'value']);

        // Should accept interface return types
        $mail->supply(fn(): \DateTimeInterface => new \DateTime());

        $this->assertTrue(true, 'All complex return types should be accepted');
    }

    public function testRejectsMultipleFactoriesWithoutReturnTypes(): void
    {
        $mail = new ChainMail();

        $this->expectException(TypeError::class);

        $mail->supply(
            fn(): string => 'valid',
            function () {
                return 'invalid';
            },
            fn(): int => 42
        );
    }

    public function testValidatesReturnTypeBeforeAddingToContainer(): void
    {
        $mail = new ChainMail();

        try {
            $mail->supply(function () {
                return 'no return type';
            });
            $this->fail('Should have thrown TypeError');
        } catch (TypeError $e) {
            $this->assertStringContainsString('return type', $e->getMessage());
        }

        // Container should still be usable after error
        $mail->supply(fn(): string => 'valid');

        $received = null;
        $mail->use(function (string $str) use (&$received) {
            $received = $str;
        });

        $mail->boot();

        $this->assertEquals('valid', $received);
    }

    public function testAcceptsNullableReturnTypes(): void
    {
        $mail = new ChainMail();

        // Nullable return types should work
        $mail->supply(fn(): ?string => 'value');
        $mail->supply(fn(): ?\DateTime => new \DateTime());

        $this->assertTrue(true, 'Nullable return types should be accepted');
    }
}
