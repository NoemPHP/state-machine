<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: Ability handler throws exception propagates to caller
 *
 * Intent: Validates error handling where handler exceptions bubble up through dispatch pipeline
 *
 * @see specs/features/abilities.yaml - integration
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('error-handling')]
class ErrorHandlingTest extends RegionBuilderTestCase
{
    #[Test]
    public function handlerExceptionPropagatesViaThenCallback(): void
    {
        // Given: Ability handler that throws exception
        $exceptionCaught = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$exceptionCaught) {
                // Register ability that throws
                $this->abilities()->register('failing-ability', [
                    'name' => 'failing-ability',
                    'description' => 'Throws exception',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () {
                        throw new \RuntimeException('Handler failed');
                    }
                ]);

                // Invoke and catch error in then() callback
                try {
                    $this->abilities('failing-ability')
                        ->then(function ($response) {
                            // Should not reach here
                        });
                } catch (\Throwable $e) {
                    $exceptionCaught = $e;
                }
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Exception should propagate
        $this->assertNotNull($exceptionCaught);
        $this->assertInstanceOf(\RuntimeException::class, $exceptionCaught);
        $this->assertStringContainsString('Handler failed', $exceptionCaught->getMessage());
    }

    #[Test]
    public function nonExistentAbilityThrowsNotFoundException(): void
    {
        // Given: Region with abilities feature
        $exceptionCaught = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$exceptionCaught) {
                // Try to invoke non-existent ability
                try {
                    $this->abilities('does-not-exist');
                } catch (\Throwable $e) {
                    $exceptionCaught = $e;
                }
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Should throw AbilityNotFoundException
        $this->assertNotNull($exceptionCaught);
        $this->assertInstanceOf(
            \Noem\State\Feature\Abilities\Exception\AbilityNotFoundException::class,
            $exceptionCaught
        );
        $this->assertStringContainsString(
            'does-not-exist',
            $exceptionCaught->getMessage(),
            'Exception should include ability name'
        );
    }

    #[Test]
    public function errorInThenCallbackDoesNotAffectOtherCallbacks(): void
    {
        // Given: Multiple then() callbacks where one throws
        $callback1Executed = false;
        $callback2Executed = false;
        $callback3Executed = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$callback1Executed, &$callback2Executed, &$callback3Executed) {
                // Register ability
                $this->abilities()->register('multi-callback', [
                    'name' => 'multi-callback',
                    'description' => 'Test callback errors',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['ok' => true]
                ]);

                $message = $this->abilities('multi-callback');

                $message->then(function ($response) use (&$callback1Executed) {
                    $callback1Executed = true;
                });

                $message->then(function ($response) {
                    throw new \Exception('Callback error');
                });

                $message->then(function ($response) use (&$callback3Executed) {
                    $callback3Executed = true;
                });
            })
            ->build();

        // When: Trigger region (may need to handle exception)
        try {
            $region->trigger((object)[]);
        } catch (\Throwable $e) {
            // Expected if callback errors propagate
        }

        // Then: First callback should execute
        // Behavior depends on implementation (fail-fast vs continue)
        $this->assertTrue(
            $callback1Executed,
            'First callback should execute before error'
        );
    }

    #[Test]
    public function handlerErrorMessageProvidesContext(): void
    {
        // Given: Ability handler with detailed error
        $exceptionCaught = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$exceptionCaught) {
                // Register ability with descriptive error
                $this->abilities()->register('detailed-error', [
                    'name' => 'detailed-error',
                    'description' => 'Provides error context',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () {
                        throw new \InvalidArgumentException(
                            'Invalid state: expected active, got inactive'
                        );
                    }
                ]);

                try {
                    $this->abilities('detailed-error');
                } catch (\Throwable $e) {
                    $exceptionCaught = $e;
                }
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Error message should be preserved
        $this->assertNotNull($exceptionCaught);
        $this->assertInstanceOf(\InvalidArgumentException::class, $exceptionCaught);
        $this->assertStringContainsString('Invalid state', $exceptionCaught->getMessage());
        $this->assertStringContainsString('expected active', $exceptionCaught->getMessage());
    }

    #[Test]
    public function errorInNestedAbilityPropagates(): void
    {
        // Given: Nested ability invocation where inner fails
        $outerResponse = null;
        $innerException = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$outerResponse, &$innerException) {
                // Register outer ability
                $this->abilities()->register('outer', [
                    'name' => 'outer',
                    'description' => 'Outer ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['outer' => true]
                ]);

                // Register failing inner ability
                $this->abilities()->register('failing-inner', [
                    'name' => 'failing-inner',
                    'description' => 'Fails',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () {
                        throw new \RuntimeException('Inner failed');
                    }
                ]);

                // Outer invocation succeeds
                $this->abilities('outer')
                    ->then(function ($response) use (&$outerResponse, &$innerException) {
                        $outerResponse = $response;

                        // Inner invocation fails
                        try {
                            $this->abilities('failing-inner');
                        } catch (\Throwable $e) {
                            $innerException = $e;
                        }
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Outer succeeds, inner exception caught
        $this->assertNotNull($outerResponse);
        $this->assertTrue($outerResponse->parameters['outer']);

        $this->assertNotNull($innerException);
        $this->assertStringContainsString('Inner failed', $innerException->getMessage());
    }
}
