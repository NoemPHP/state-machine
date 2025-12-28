<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Middleware;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Middleware logs ability invocations with timing
 *
 * Tests a real-world middleware example: logging middleware that records
 * ability invocations, parameters, execution time, and results. This demonstrates
 * how middleware can wrap invocations for observability without modifying
 * the core ability logic.
 *
 * This is a practical example showing how the middleware interception pattern
 * enables cross-cutting concerns like logging, metrics, and monitoring.
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('middleware')]
#[Group('example')]
class LoggingMiddlewareTest extends TestCase
{
    public function testLoggingMiddlewareRecordsInvocationDetails(): void
    {
        // Arrange
        $abilityName = 'get-user-profile';
        $parameters = ['userId' => 123];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Get user profile',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['name' => 'John Doe', 'email' => 'john@example.com']
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $logEntries = [];

        // Logging middleware
        $loggingMiddleware = function (InvokeAbilityParams $params, $next, $first) use (&$logEntries) {
            $startTime = microtime(true);

            // Log invocation start
            $logEntries[] = [
                'event' => 'invocation_start',
                'ability' => $params->abilityName,
                'parameters' => $params->parameters,
                'timestamp' => $startTime,
            ];

            // Execute the chain
            $result = $next($params);

            $endTime = microtime(true);
            $duration = $endTime - $startTime;

            // Log invocation end
            $logEntries[] = [
                'event' => 'invocation_end',
                'ability' => $params->abilityName,
                'duration_ms' => round($duration * 1000, 2),
                'timestamp' => $endTime,
            ];

            return $result;
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($loggingMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: $parameters
        );

        // Act
        $invokeAbility->call($params);

        // Assert
        $this->assertCount(2, $logEntries, 'Should log start and end events');

        // Verify start log entry
        $this->assertSame('invocation_start', $logEntries[0]['event']);
        $this->assertSame($abilityName, $logEntries[0]['ability']);
        $this->assertSame($parameters, $logEntries[0]['parameters']);
        $this->assertArrayHasKey('timestamp', $logEntries[0]);

        // Verify end log entry
        $this->assertSame('invocation_end', $logEntries[1]['event']);
        $this->assertSame($abilityName, $logEntries[1]['ability']);
        $this->assertArrayHasKey('duration_ms', $logEntries[1]);
        $this->assertGreaterThanOrEqual(0, $logEntries[1]['duration_ms']);
    }

    public function testLoggingMiddlewareTracksErrors(): void
    {
        // Arrange
        $abilityName = 'failing-ability';

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Failing ability',
            parameterSchema: [],
            responseSchema: [],
            handler: function ($params) {
                throw new \RuntimeException('Simulated failure');
            }
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willThrowException(new \RuntimeException('Simulated failure'));

        $logEntries = [];

        // Error-tracking logging middleware
        $loggingMiddleware = function ($params, $next, $first) use (&$logEntries) {
            try {
                $result = $next($params);
                $logEntries[] = ['event' => 'success', 'ability' => $params->abilityName];
                return $result;
            } catch (\Throwable $e) {
                $logEntries[] = [
                    'event' => 'error',
                    'ability' => $params->abilityName,
                    'error_message' => $e->getMessage(),
                    'error_class' => get_class($e),
                ];
                throw $e; // Re-throw after logging
            }
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($loggingMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        // Act & Assert
        try {
            $invokeAbility->call($params);
            $this->fail('Expected exception to be thrown');
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Verify error was logged
        $this->assertCount(1, $logEntries);
        $this->assertSame('error', $logEntries[0]['event']);
        $this->assertSame($abilityName, $logEntries[0]['ability']);
        $this->assertSame('Simulated failure', $logEntries[0]['error_message']);
        $this->assertSame(\RuntimeException::class, $logEntries[0]['error_class']);
    }

    public function testLoggingMiddlewareCountsInvocations(): void
    {
        // Arrange
        $abilityName = 'increment-counter';

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Increment counter',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $invocationCounts = [];

        // Counting middleware
        $countingMiddleware = function ($params, $next, $first) use (&$invocationCounts) {
            $abilityName = $params->abilityName;

            if (!isset($invocationCounts[$abilityName])) {
                $invocationCounts[$abilityName] = 0;
            }

            $invocationCounts[$abilityName]++;

            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($countingMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        // Act - invoke multiple times
        $invokeAbility->call($params);
        $invokeAbility->call($params);
        $invokeAbility->call($params);

        // Assert
        $this->assertArrayHasKey($abilityName, $invocationCounts);
        $this->assertSame(3, $invocationCounts[$abilityName]);
    }

    public function testLoggingMiddlewareRecordsContextInformation(): void
    {
        // Arrange
        $abilityName = 'send-email';
        $parameters = ['to' => 'user@example.com', 'subject' => 'Test'];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Send email',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['sent' => true]
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $logEntries = [];

        // Context-aware logging middleware
        $loggingMiddleware = function ($params, $next, $first) use (&$logEntries) {
            $logEntries[] = [
                'ability' => $params->abilityName,
                'region_id' => spl_object_id($params->region),
                'has_parameters' => $params->parameters !== null,
                'parameter_count' => $params->parameters ? count((array)$params->parameters) : 0,
                'timestamp' => time(),
            ];

            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($loggingMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: $parameters
        );

        // Act
        $invokeAbility->call($params);

        // Assert
        $this->assertCount(1, $logEntries);
        $this->assertSame($abilityName, $logEntries[0]['ability']);
        $this->assertSame(spl_object_id($region), $logEntries[0]['region_id']);
        $this->assertTrue($logEntries[0]['has_parameters']);
        $this->assertSame(2, $logEntries[0]['parameter_count']);
    }

    public function testLoggingMiddlewareDoesNotModifyResult(): void
    {
        // Arrange
        $abilityName = 'get-data';
        $expectedResult = AbilityMessage::create(
            abilityName: $abilityName,
            parameters: ['data' => 'value'],
            definition: null
        );

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Get data',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['data' => 'value']
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $logEntries = [];

        // Non-intrusive logging middleware
        $loggingMiddleware = function ($params, $next, $first) use (&$logEntries) {
            $logEntries[] = ['logged' => true];
            // Middleware logs but doesn't modify the result
            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($loggingMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        // Act
        $result = $invokeAbility->call($params);

        // Assert
        $this->assertCount(1, $logEntries, 'Middleware should have logged');
        // Result should be unmodified by logging middleware
        $this->assertInstanceOf(AbilityMessage::class, $result);
    }
}
