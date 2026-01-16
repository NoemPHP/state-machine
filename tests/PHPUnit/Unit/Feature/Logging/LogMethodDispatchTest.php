<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Logging;

use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\Logging\LoggingChain;
use Noem\State\Feature\Logging\LoggingFeature;
use Noem\State\Feature\Logging\LogParams;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoggingFeature::class)]
class LogMethodDispatchTest extends TestCase
{
    public function testLogMethodDispatchesLogParamsThroughLoggingChain(): void
    {
        $region = $this->createMock(Region::class);
        $region->method('currentState')->willReturn('testState');

        $chainMail = new ChainMail();

        // Track if LogParams was dispatched through LoggingChain
        $dispatchedParams = null;

        // Register BoundAccess
        $boundAccess = new BoundAccess();
        $chainMail->supply(fn(): BoundAccess => $boundAccess);

        // Invoke feature (which registers LoggingChain and binds $this->log())
        $feature = new LoggingFeature();
        $feature->__invoke($chainMail);

        // Boot ChainMail to execute middleware registration
        $chainMail->boot();

        // Now get the LoggingChain that was created by the feature
        $loggingChain = $chainMail->get(LoggingChain::class);

        // Add tracking middleware to LoggingChain
        $loggingChain->link(function (LogParams $params, callable $next) use (&$dispatchedParams) {
            $dispatchedParams = $params;
            return $next($params);
        });

        // Simulate calling $this->log() via BoundAccess
        $boundAccessParams = new BoundAccessParams(
            region: $region,
            type: BoundAccessParams::TYPE_METHOD,
            name: 'log',
            payload: ['info', 'Test message', ['key' => 'value']]
        );

        $boundAccess->call($boundAccessParams);

        // Verify LogParams was dispatched with correct data
        $this->assertInstanceOf(LogParams::class, $dispatchedParams);
        $this->assertSame('info', $dispatchedParams->level);
        $this->assertSame('Test message', $dispatchedParams->message);
        $this->assertSame(['key' => 'value'], $dispatchedParams->context);
        $this->assertSame($region, $dispatchedParams->region);
    }
}
