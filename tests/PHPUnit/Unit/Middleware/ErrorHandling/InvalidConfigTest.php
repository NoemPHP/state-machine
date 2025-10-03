<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ErrorHandling;

use Noem\State\Middleware\Chain;
use Noem\State\Middleware\ChainException;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * Acceptance Criterion: Invalid middleware configurations are caught early
 */
#[Group('middleware')]
#[Group('error-handling')]
class InvalidConfigTest extends TestCase
{
    public function testZeroMaxRestartsIsValid(): void
    {
        // Zero should be a valid value (no restarts allowed)
        $chain = new Chain(fn($c) => $c, [], 0);

        $result = $chain->call('test');

        $this->assertEquals('test', $result);
    }

    public function testNegativeMaxRestartsIsAccepted(): void
    {
        // Chain doesn't validate negative maxRestarts, but it would prevent any restarts
        $chain = new Chain(fn($c) => $c, [], -1);

        $result = $chain->call('test');

        $this->assertEquals('test', $result);
    }

    public function testInvalidMiddlewareTypeThrowsTypeError(): void
    {
        $this->expectException(TypeError::class);

        // Pass non-array as middleware parameter
        new Chain(fn($c) => $c, 'not-an-array');
    }

    public function testInvalidProviderTypeThrowsTypeError(): void
    {
        $this->expectException(TypeError::class);

        // Pass non-callable as provider
        new Chain('not-a-callable');
    }

    public function testChainMailRejectsFactoriesWithoutReturnType(): void
    {
        $mail = new ChainMail();

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('return type');

        // Factory without return type
        $mail->supply(function () {
            return 'value';
        });
    }

    public function testChainMailRejectsInvalidCallableInUse(): void
    {
        $mail = new ChainMail();

        $this->expectException(TypeError::class);

        // Pass non-callable to use()
        $mail->use('not-a-callable');
    }

    public function testChainMailRejectsInvalidCallableInSupply(): void
    {
        $mail = new ChainMail();

        $this->expectException(TypeError::class);

        // Pass non-callable to supply()
        $mail->supply('not-a-callable');
    }

    public function testChainRejectsInvalidCallableInLink(): void
    {
        $chain = new Chain(fn($c) => $c);

        $this->expectException(TypeError::class);

        // Pass non-callable to link()
        $chain->link('not-a-callable');
    }

    public function testChainRejectsInvalidCallableInWithProvider(): void
    {
        $chain = new Chain(fn($c) => $c);

        $this->expectException(TypeError::class);

        // Pass non-callable to withProvider()
        $chain->withProvider('not-a-callable');
    }

    public function testValidConfigurationDoesNotThrow(): void
    {
        // Valid configuration should work without issues
        $chain = new Chain(
            fn($c) => "result:$c",
            [
                fn($c, $next) => $next($c) . '-1',
                fn($c, $next) => $next($c) . '-2',
            ],
            5
        );

        $result = $chain->call('test');

        $this->assertStringContainsString('result:test', $result);
        $this->assertStringContainsString('-2', $result);
        $this->assertStringContainsString('-1', $result);
    }

    public function testChainMailValidConfigurationDoesNotThrow(): void
    {
        $mail = new ChainMail();

        // Valid configuration
        $mail->supply(fn(): string => 'value');
        $mail->supply(fn(): int => 42);

        $executed = false;
        $mail->use(function (string $str, int $num) use (&$executed) {
            $executed = true;
        });

        $mail->boot();

        $this->assertTrue($executed);
    }
}
