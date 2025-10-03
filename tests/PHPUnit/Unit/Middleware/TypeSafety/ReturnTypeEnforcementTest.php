<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\TypeSafety;

use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * Acceptance Criterion: Return type enforcement for factory callables
 */
#[Group('middleware')]
#[Group('type-safety')]
class ReturnTypeEnforcementTest extends TestCase
{
    public function testFactoryRequiresReturnType(): void
    {
        $this->expectException(TypeError::class);

        $mail = new ChainMail();

        // Factory without return type should throw TypeError
        $mail->supply(function () {
            return 'value';
        });
    }

    public function testFactoryWithReturnTypeAccepted(): void
    {
        $mail = new ChainMail();

        // Factory with return type should work
        $mail->supply(fn(): string => 'value');

        $mail->boot();

        $result = $mail->get('string');

        $this->assertEquals('value', $result);
    }

    public function testMultipleFactoriesWithReturnTypes(): void
    {
        $mail = new ChainMail();

        $mail->supply(
            fn(): string => 'text',
            fn(): int => 42,
            fn(): array => [1, 2, 3]
        );

        $mail->boot();

        $this->assertEquals('text', $mail->get('string'));
        $this->assertEquals(42, $mail->get('int'));
        $this->assertEquals([1, 2, 3], $mail->get('array'));
    }

    public function testReturnTypeEnforcedAtRuntime(): void
    {
        $mail = new ChainMail();

        $mail->supply(fn(): string => 'correct type');

        $mail->boot();

        $result = $mail->get('string');

        $this->assertIsString($result);
    }
}
