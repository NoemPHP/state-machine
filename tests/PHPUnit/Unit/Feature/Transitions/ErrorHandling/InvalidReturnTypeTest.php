<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\ErrorHandling;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * Acceptance Criterion: Invalid guard return type throws RuntimeException with context
 */
#[Group('transitions')]
#[Group('error-handling')]
class InvalidReturnTypeTest extends TestCase
{
    public function testInvalidReturnTypeThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid guard callback/');

        $region = (new RegionBuilder())
            ->setStates('start', 'end')
            ->markInitial('start')
            // Guard without bool return type - invalid!
            ->addBuildStep(new AddTransition('start', 'end', fn(object $t) => 'not a bool'))
            ->build();

        $region->trigger(new stdClass());
    }

    public function testErrorMessageIncludesTransitionContext(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/from 'start' to 'end'/");

        $region = (new RegionBuilder())
            ->setStates('start', 'end')
            ->markInitial('start')
            ->addBuildStep(new AddTransition('start', 'end', fn(object $t): int => 1))
            ->build();

        $region->trigger(new stdClass());
    }

    public function testGuardMustReturnBool(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must return bool/');

        $region = (new RegionBuilder())
            ->setStates('a', 'b')
            ->markInitial('a')
            ->addBuildStep(new AddTransition('a', 'b', fn(object $t): string => 'true'))
            ->build();

        $region->trigger(new stdClass());
    }
}
