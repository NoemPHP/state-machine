<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\Evaluation;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Feature\Transitions\TransitionRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

class DebugTest extends TestCase
{
    public function testDebugGuardStorage(): void
    {
        $builder = new RegionBuilder();
        $callOrder = [];

        $region = $builder
            ->setStates('start', 'end')
            ->markInitial('start')
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'guard1';
                return false;
            }))
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'guard2';
                return false;
            }))
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'guard3';
                return false;
            }))
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'guard4';
                return true;
            }))
            ->build();

        // Get the registry from the builder's ChainMail
        $registry = $builder->chainMail->get(TransitionRegistry::class);
        $transitions = $registry->getTransitionsForState($region, 'start');
        
        echo "\n=== DEBUG INFO ===\n";
        echo "Transitions structure:\n";
        var_dump($transitions);
        echo "Number of targets: " . count($transitions) . "\n";
        if (isset($transitions['end'])) {
            echo "Number of guards for 'end': " . count($transitions['end']) . "\n";
        }
        echo "==================\n\n";

        $this->assertArrayHasKey('end', $transitions);
        $this->assertCount(4, $transitions['end'], 'Should have 4 guards');
        
        // Now trigger and see what happens
        $region->trigger((object)[]);
        
        echo "\n=== CALL ORDER ===\n";
        var_dump($callOrder);
        echo "==================\n\n";
        
        $this->assertEquals(['guard1', 'guard2', 'guard3', 'guard4'], $callOrder);
    }
}
