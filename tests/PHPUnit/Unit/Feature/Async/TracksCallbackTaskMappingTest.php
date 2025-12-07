<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature tracks Task-to-callback mapping
 */
#[Group('async'), Group('async-callback-handling')]
class TracksCallbackTaskMappingTest extends TestCase
{
    public function testTracksCallbackTaskMapping(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $yields = [];

        $region = $builder
            ->setStates('idle')
            ->onAction('idle', function (object $trigger) use (&$yields) {
                $yields[] = 'first';
                yield 'value1';
                $yields[] = 'second';
                yield 'value2';
                $yields[] = 'third';
            })
            ->build();

        // First trigger - creates task and executes first yield
        $region->trigger(new \stdClass());
        $this->assertSame(['first'], $yields);

        // Second trigger - should resume the same task (not create a new one)
        $region->trigger(new \stdClass());
        $this->assertSame(['first', 'second'], $yields);

        // Third trigger - continues the same task
        $region->trigger(new \stdClass());
        $this->assertSame(['first', 'second', 'third'], $yields);
    }
}
