<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature enqueues new generators as tasks
 */
#[Group('async'), Group('async-callback-handling')]
class EnqueuesNewGeneratorsTest extends TestCase
{
    public function testEnqueuesNewGenerators(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());
        
        $generatorCreated = false;
        $yieldExecuted = false;
        
        $region = $builder
            ->setStates('idle')
            ->onAction('idle', function (object $trigger) use (&$generatorCreated, &$yieldExecuted) {
                $generatorCreated = true;
                $yieldExecuted = true;
                yield 'value';
            })
            ->build();
        
        $this->assertFalse($generatorCreated);
        $this->assertFalse($yieldExecuted);
        
        // Trigger should enqueue the generator as a task
        $region->trigger(new \stdClass());
        
        $this->assertTrue($generatorCreated, 'Generator should have been created');
        $this->assertTrue($yieldExecuted, 'Yield should have been executed');
    }
    
    public function testEnqueuesMultipleGenerators(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());
        
        $executionLog = [];
        
        $region = $builder
            ->setStates('idle')
            ->onAction('idle', function (object $trigger) use (&$executionLog) {
                $executionLog[] = 'gen1-start';
                yield 'gen1-val1';
                $executionLog[] = 'gen1-middle';
                yield 'gen1-val2';
                $executionLog[] = 'gen1-end';
            })
            ->onAction('idle', function (object $trigger) use (&$executionLog) {
                $executionLog[] = 'gen2-start';
                yield 'gen2-val1';
                $executionLog[] = 'gen2-middle';
                yield 'gen2-val2';
                $executionLog[] = 'gen2-end';
            })
            ->build();
        
        // Both generators should be enqueued and execute cooperatively
        $region->trigger(new \stdClass());
        
        // After one trigger, both should have started
        $this->assertContains('gen1-start', $executionLog);
        $this->assertContains('gen2-start', $executionLog);
    }
}
