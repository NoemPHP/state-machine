<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Machine;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Machine execute method runs region until final state
 */
#[Group('loader')]
#[Group('machine-abstraction')]
class MachineExecuteTest extends TestCase
{
    public function testExecuteRunsUntilFinalState(): void
    {
        $triggerCount = 0;
        
        $machine = new class($triggerCount) extends Machine {
            public function __construct(private int &$count)
            {
            }
            
            public function yaml(): string
            {
                return <<<YAML
                states:
                  - name: start
                    transitions:
                      - target: middle
                  - name: middle
                    transitions:
                      - target: end
                  - name: end
                initial: start
                final: end
                YAML;
            }
            
            public function trigger(): object
            {
                $this->count++;
                $trigger = new \stdClass();
                $trigger->id = $this->count;
                return $trigger;
            }
            
            public function features(): iterable
            {
                return [
                    parent::features()[0],
                    new TransitionsFeature(),
                ];
            }
        };
        
        $builder = new RegionBuilder();
        $region = $builder
            ->enableFeatures(...$machine->features())
            ->build($machine->builderArgs());
        
        $result = $machine->execute($region);
        
        $this->assertTrue($region->isFinal());
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('id', $result);
        // Should have triggered at least twice to get from start -> middle -> end
        $this->assertGreaterThanOrEqual(2, $triggerCount);
    }
}
