<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\DoTransition;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\DoTransition;
use Noem\State\Chains\Params\Transition;
use Noem\State\Events;
use Noem\State\Middleware\Chain;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: DoTransition implements Chain interface
 */
#[Group('transitions')]
#[Group('transition-execution')]
class ExecutesAsChainTest extends TestCase
{
    public function testDoTransitionExtendsChain(): void
    {
        $events = \Mockery::mock(Events::class);
        $connectedRegions = \Mockery::mock(ConnectedRegions::class);
        
        $doTransition = new DoTransition($connectedRegions, $events);
        
        $this->assertInstanceOf(Chain::class, $doTransition);
        
        \Mockery::close();
    }
    
    public function testDoTransitionCanBeLinkingMiddleware(): void
    {
        $events = \Mockery::mock(Events::class);
        $events->shouldReceive('onExitState')->once();
        $events->shouldReceive('onEnterState')->once();
        
        $connectedRegions = \Mockery::mock(ConnectedRegions::class);
        $connectedRegions->shouldReceive('call')->andReturn([]);
        
        $doTransition = new DoTransition($connectedRegions, $events);
        
        $middlewareExecuted = false;
        
        // Link middleware to the chain
        $doTransition->link(function (Transition $context, callable $next) use (&$middlewareExecuted) {
            $middlewareExecuted = true;
            return $next($context);
        });
        
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        $payload = new stdClass();
        $context = new Transition($region, $payload, 'a');
        
        $doTransition->call($context);
        
        $this->assertTrue($middlewareExecuted);
        
        \Mockery::close();
    }
    
    public function testDoTransitionMiddlewareCanInterceptExecution(): void
    {
        $events = \Mockery::mock(Events::class);
        // Events should NOT be called because middleware will intercept
        $events->shouldReceive('onExitState')->never();
        $events->shouldReceive('onEnterState')->never();
        
        $connectedRegions = \Mockery::mock(ConnectedRegions::class);
        
        $doTransition = new DoTransition($connectedRegions, $events);
        
        // Link middleware that short-circuits execution
        $doTransition->link(function (Transition $context, callable $next) {
            // Don't call next - short circuit
            return null;
        });
        
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        $payload = new stdClass();
        $context = new Transition($region, $payload, 'a');
        
        $result = $doTransition->call($context);
        
        $this->assertNull($result);
        
        \Mockery::close();
    }
    
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
