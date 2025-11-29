<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Call;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Call.call nests coroutines and returns child result to parent
 */
#[Group('async'), Group('integration')]
class NestedCoroutinesTest extends TestCase
{
    public function testCallNestsCoroutinesAndReturnsChildResult(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());
        
        $log = [];
        
        $region = $builder
            ->setStates('active')
            ->onAction('active', function (object $event) use (&$log) {
                $log[] = 'parent-start';
                yield;
                
                $result = yield Call::call(function () use (&$log) {
                    $log[] = 'child-start';
                    yield;
                    $log[] = 'child-middle';
                    yield;
                    $log[] = 'child-end';
                    return 'child-result';
                });
                
                $log[] = 'parent-resume';
                $event->result = $result;
                yield;
            })
            ->build();
        
        $event = (object)['result' => null];
        
        // Execute multiple triggers to progress through nested coroutines
        for ($i = 0; $i < 10; $i++) {
            $region->trigger($event);
        }
        
        // Verify execution order
        $this->assertContains('parent-start', $log);
        $this->assertContains('child-start', $log);
        $this->assertContains('child-middle', $log);
        $this->assertContains('child-end', $log);
        $this->assertContains('parent-resume', $log);
        
        // Verify child result was returned to parent
        $this->assertSame('child-result', $event->result);
        
        // Verify parent resumed after child completed
        $childEndIndex = array_search('child-end', $log);
        $parentResumeIndex = array_search('parent-resume', $log);
        $this->assertLessThan($parentResumeIndex, $childEndIndex, 'Parent should resume after child completes');
    }
}
