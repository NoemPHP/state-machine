<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\ErrorHandling;

use Noem\State\Middleware\Chain;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Proper handling of null and undefined values in contexts
 */
#[Group('middleware')]
#[Group('error-handling')]
class NullContextTest extends TestCase
{
    public function testChainHandlesNullContext(): void
    {
        $chain = new Chain(fn($c) => "result:$c");

        $result = $chain->call(null);

        $this->assertEquals('result:', $result);
    }

    public function testChainMiddlewareCanPassNullContext(): void
    {
        $receivedNull = false;

        $middleware = function ($context, $next) use (&$receivedNull) {
            if ($context === null) {
                $receivedNull = true;
            }

            $nullValue = null;
            return $next($nullValue);
        };

        $chain = new Chain(fn($c) => $c, [$middleware]);

        $result = $chain->call(null);

        $this->assertTrue($receivedNull);
        $this->assertNull($result);
    }

    public function testChainHandlesNullReturnFromMiddleware(): void
    {
        $middleware = fn($context, $next) => null;

        $chain = new Chain(fn($c) => "base:$c", [$middleware]);

        $result = $chain->call('test');

        $this->assertNull($result);
    }

    public function testChainHandlesNullProvider(): void
    {
        $chain = new Chain(fn($c) => null);

        $result = $chain->call('test');

        $this->assertNull($result);
    }

    public function testChainMailHandlesNullableParameters(): void
    {
        $mail = new ChainMail();

        // Don't supply DateTime, but use nullable parameter
        $receivedNull = false;
        $mail->use(function (?\DateTime $dateTime) use (&$receivedNull) {
            if ($dateTime === null) {
                $receivedNull = true;
            }
        });

        $mail->boot();

        $this->assertTrue($receivedNull, 'Nullable parameter should receive null');
    }

    public function testChainMailHandlesNullableParametersWithOtherServices(): void
    {
        $mail = new ChainMail();

        // Supply string but not DateTime
        $mail->supply(fn(): string => 'value');

        $receivedValues = [];
        $mail->use(function (string $str, ?\DateTime $dateTime) use (&$receivedValues) {
            $receivedValues['str'] = $str;
            $receivedValues['dateTime'] = $dateTime;
        });

        $mail->boot();

        $this->assertEquals('value', $receivedValues['str']);
        $this->assertNull($receivedValues['dateTime']);
    }

    public function testChainPreservesNullThroughMultipleMiddleware(): void
    {
        $nullCount = 0;

        $middleware1 = function ($context, $next) use (&$nullCount) {
            if ($context === null) {
                $nullCount++;
            }

            return $next($context);
        };

        $middleware2 = function ($context, $next) use (&$nullCount) {
            if ($context === null) {
                $nullCount++;
            }

            return $next($context);
        };

        $chain = new Chain(fn($c) => $c, [$middleware1, $middleware2]);

        $result = $chain->call(null);

        $this->assertNull($result);
        $this->assertEquals(2, $nullCount);
    }

    public function testChainHandlesUndefinedArrayOffsets(): void
    {
        $context = ['key' => 'value'];

        $middleware = function ($context, $next) {
            // Access undefined offset - should not throw error
            $undefined = $context['undefined'] ?? 'default';

            return $next($undefined);
        };

        $chain = new Chain(fn($c) => "result:$c", [$middleware]);

        $result = $chain->call($context);

        $this->assertEquals('result:default', $result);
    }

    public function testChainHandlesNullInRestartScenario(): void
    {
        $executed = [];

        $middleware = function ($context, $next, $first) use (&$executed) {
            static $restarted;

            $executed[] = $context;

            if (!$restarted) {
                $restarted = true;

                return $first(null);
            }

            return $next($context);
        };

        $chain = new Chain(fn($c) => "final:$c", [$middleware], 1);

        $result = $chain->call('initial');

        $this->assertEquals(['initial', null], $executed);
        $this->assertEquals('final:', $result);
    }

    public function testChainMailGetReturnsNullForMissingNullableType(): void
    {
        $mail = new ChainMail();

        // Attempting to get a service that doesn't exist with proper error handling
        $this->expectException(\Noem\State\Middleware\ChainException::class);

        $mail->get(\DateTime::class);
    }
}
