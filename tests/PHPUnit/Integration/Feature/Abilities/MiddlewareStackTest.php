<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: Multiple middleware (logging, auth, caching) work together
 *
 * Intent: Validates middleware stack with multiple interceptors working in harmony
 *
 * @see specs/features/abilities.yaml - integration
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('middleware')]
class MiddlewareStackTest extends RegionBuilderTestCase
{
    #[Test]
    public function multipleMiddlewareExecuteInOrder(): void
    {
        // Given: Region with multiple middleware registered
        $executionOrder = [];
        $response = null;

        // Access InvokeAbility chain and add middleware BEFORE building region
        $invokeChain = $this->chainmail()->get(InvokeAbility::class);

        // Add logging middleware
        $invokeChain->link(function ($params, $next) use (&$executionOrder) {
            $executionOrder[] = 'logging-before';
            $result = $next($params);
            $executionOrder[] = 'logging-after';
            return $result;
        });

        // Add auth middleware
        $invokeChain->link(function ($params, $next) use (&$executionOrder) {
            $executionOrder[] = 'auth-before';
            $result = $next($params);
            $executionOrder[] = 'auth-after';
            return $result;
        });

        // Add caching middleware
        $invokeChain->link(function ($params, $next) use (&$executionOrder) {
            $executionOrder[] = 'cache-before';
            $result = $next($params);
            $executionOrder[] = 'cache-after';
            return $result;
        });

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$response) {
                // Register ability
                $this->abilities()->register('test', [
                    'name' => 'test',
                    'description' => 'Test middleware',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['ok' => true]
                ]);

                // Invoke ability
                $this->abilities('test')
                    ->then(function ($data) use (&$response) {
                        $response = $data;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Middleware should execute in registration order (FIFO)
        // before handlers, then reverse order after
        $this->assertNotNull($response);
        $this->assertCount(6, $executionOrder);
        $this->assertSame('logging-before', $executionOrder[0]);
        $this->assertSame('auth-before', $executionOrder[1]);
        $this->assertSame('cache-before', $executionOrder[2]);
        $this->assertSame('cache-after', $executionOrder[3]);
        $this->assertSame('auth-after', $executionOrder[4]);
        $this->assertSame('logging-after', $executionOrder[5]);
    }

    #[Test]
    public function middlewareCanModifyParameters(): void
    {
        // Given: Middleware that modifies parameters
        $receivedParams = null;

        // Add middleware that injects additional parameters BEFORE building region
        $invokeChain = $this->chainmail()->get(InvokeAbility::class);

        $invokeChain->link(function ($params, $next) {
            // Modify params before validation
            $modifiedParams = new \Noem\State\Feature\Abilities\Params\InvokeAbility(
                $params->region,
                $params->abilityName,
                array_merge((array)$params->parameters, ['injected' => true])
            );
            return $next($modifiedParams);
        });

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$receivedParams) {
                // Register ability
                $this->abilities()->register('param-test', [
                    'name' => 'param-test',
                    'description' => 'Test parameter modification',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function ($params) use (&$receivedParams) {
                        $receivedParams = $params;
                        return [];
                    }
                ]);

                // Invoke with original parameters
                $this->abilities('param-test', ['original' => true]);
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Handler should receive modified parameters
        $this->assertNotNull($receivedParams);
        $this->assertArrayHasKey('original', $receivedParams);
        $this->assertArrayHasKey('injected', $receivedParams);
        $this->assertTrue($receivedParams['injected']);
    }

    #[Test]
    public function authMiddlewareCanBlockInvocation(): void
    {
        // Given: Auth middleware that rejects invocation
        $handlerExecuted = false;
        $blocked = false;

        // Add auth middleware BEFORE building region
        $invokeChain = $this->chainmail()->get(InvokeAbility::class);

        $invokeChain->link(function ($params, $next) use (&$blocked) {
            // Check authorization (simplified)
            if ($params->abilityName === 'protected') {
                $blocked = true;
                throw new \RuntimeException('Unauthorized');
            }
            return $next($params);
        });

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$handlerExecuted) {
                // Register protected ability
                $this->abilities()->register('protected', [
                    'name' => 'protected',
                    'description' => 'Protected ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$handlerExecuted) {
                        $handlerExecuted = true;
                        return [];
                    }
                ]);

                // Try to invoke protected ability
                try {
                    $this->abilities('protected');
                } catch (\Throwable $e) {
                    // Expected
                }
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Handler should not execute (blocked by middleware)
        $this->assertTrue($blocked);
        $this->assertFalse($handlerExecuted);
    }

    #[Test]
    public function cachingMiddlewareShortCircuits(): void
    {
        // Given: Caching middleware
        $handlerCallCount = 0;
        $responses = [];

        // Simple cache
        $cache = [];

        // Add caching middleware BEFORE building region
        $invokeChain = $this->chainmail()->get(InvokeAbility::class);

        $invokeChain->link(function ($params, $next) use (&$cache) {
            $cacheKey = $params->abilityName;

            // Check cache
            if (isset($cache[$cacheKey])) {
                // Return cached response (short-circuit)
                return $cache[$cacheKey];
            }

            // Call handler and cache result
            $result = $next($params);
            $cache[$cacheKey] = $result;
            return $result;
        });

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$handlerCallCount, &$responses) {
                // Register ability
                $this->abilities()->register('expensive', [
                    'name' => 'expensive',
                    'description' => 'Expensive operation',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$handlerCallCount) {
                        $handlerCallCount++;
                        return ['computed' => time()];
                    }
                ]);

                // Invoke twice
                $this->abilities('expensive')
                    ->then(function ($data) use (&$responses) {
                        $responses[] = $data;
                    });

                $this->abilities('expensive')
                    ->then(function ($data) use (&$responses) {
                        $responses[] = $data;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Handler should only execute once (second cached)
        $this->assertSame(1, $handlerCallCount, 'Handler should only execute once');
        $this->assertCount(2, $responses);
    }
}
