<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\ErrorHandling;

use Exception;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * Acceptance Criterion: Guard exceptions are propagated with context
 */
#[Group('transitions')]
#[Group('error-handling')]
class GuardExceptionPropagationTest extends TestCase
{
    public function testGuardExceptionIsPropagated(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Guard failed');

        $region = (new RegionBuilder())
            ->setStates('start', 'end')
            ->markInitial('start')
            ->addBuildStep(new AddTransition('start', 'end', function (object $t): bool {
                throw new RuntimeException('Guard failed');
            }))
            ->build();

        $region->trigger(new stdClass());
    }

    public function testExceptionPreservesStackTrace(): void
    {
        $exceptionThrown = false;
        $exceptionMessage = '';

        $region = (new RegionBuilder())
            ->setStates('a', 'b')
            ->markInitial('a')
            ->addBuildStep(new AddTransition('a', 'b', function (object $t): bool {
                throw new Exception('Custom error from guard');
            }))
            ->build();

        try {
            $region->trigger(new stdClass());
        } catch (Exception $e) {
            $exceptionThrown = true;
            $exceptionMessage = $e->getMessage();
        }

        $this->assertTrue($exceptionThrown);
        $this->assertStringContainsString('Custom error from guard', $exceptionMessage);
    }

    public function testMultipleGuardsStopAtFirstException(): void
    {
        $secondGuardCalled = false;

        $this->expectException(RuntimeException::class);

        $region = (new RegionBuilder())
            ->setStates('start', 'end')
            ->markInitial('start')
            // Guards evaluated in reverse order (LIFO)
            ->addBuildStep(new AddTransition('start', 'end', function (object $t) use (&$secondGuardCalled): bool {
                $secondGuardCalled = true;
                return false;
            }))
            ->addBuildStep(new AddTransition('start', 'end', function (object $t): bool {
                throw new RuntimeException('First guard throws');
            }))
            ->build();

        try {
            $region->trigger(new stdClass());
        } catch (RuntimeException $e) {
            // Second guard should not have been called
            $this->assertFalse($secondGuardCalled);
            throw $e;
        }
    }
}
