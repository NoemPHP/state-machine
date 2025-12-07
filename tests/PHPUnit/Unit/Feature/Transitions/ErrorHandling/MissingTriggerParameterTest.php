<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\ErrorHandling;

use InvalidArgumentException;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Missing trigger parameter in guard throws descriptive error
 */
#[Group('transitions')]
#[Group('error-handling')]
class MissingTriggerParameterTest extends TestCase
{
    public function testMissingTriggerParameterThrowsError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required Parameter 0 not declared');

        $region = (new RegionBuilder())
            ->setStates('start', 'end')
            ->markInitial('start')
            // Guard without trigger parameter - invalid!
            ->addBuildStep(new AddTransition('start', 'end', fn(): bool => true))
            ->build();

        $region->trigger(new stdClass());
    }

    public function testGuardWithoutParametersThrowsDescriptiveError(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $region = (new RegionBuilder())
            ->setStates('a', 'b')
            ->markInitial('a')
            ->addBuildStep(new AddTransition('a', 'b', function () {
                return true;
            }))
            ->build();

        $region->trigger(new stdClass());
    }
}
