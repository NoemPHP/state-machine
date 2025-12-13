<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Async action progresses through multiple yields across triggers
 */
#[Group('async'), Group('integration')]
class MultiStepAsyncActionTest extends TestCase
{
    public function testAsyncActionProgressesThroughMultipleYields(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $log = [];

        $region = $builder
            ->setStates('processing', 'done')
            ->markFinal('done')
            ->onAction('processing', function (object $event) use (&$log) {
                $log[] = 'step1';
                $event->data = 'initialized';
                yield;

                $log[] = 'step2';
                $event->data .= '-processing';
                yield;

                $log[] = 'step3';
                $event->data .= '-finalizing';
                yield;

                $log[] = 'step4';
                $event->data .= '-complete';
            })
            ->build();

        $event = (object)['data' => ''];

        // First trigger - all steps complete (Priority::NORMAL = 5 steps, task has 4 steps)
        $region->trigger($event);
        $this->assertSame(['step1', 'step2', 'step3', 'step4'], $log, 'All 4 steps complete in first tick');
        $this->assertSame('initialized-processing-finalizing-complete', $event->data);
    }
}
