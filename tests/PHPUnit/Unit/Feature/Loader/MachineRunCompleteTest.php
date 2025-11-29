<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Machine;
use Noem\State\Feature\Transitions\TransitionsFeature;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Machine run method executes until final state and returns last result
 */
#[Group('loader')]
#[Group('machine-abstraction')]
class MachineRunCompleteTest extends TestCase
{
    public function testRunExecutesUntilFinalStateAndReturnsLastResult(): void
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
                $trigger->step = "step-{$this->count}";
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
        
        // Use the static run method
        $result = Machine::run($machine);
        
        // Verify result is the last trigger
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('id', $result);
        $this->assertObjectHasProperty('step', $result);
        
        // Should be the last trigger object
        $this->assertSame($triggerCount, $result->id);
        $this->assertSame("step-{$triggerCount}", $result->step);
        
        // Should have triggered multiple times to reach final state
        $this->assertGreaterThanOrEqual(2, $triggerCount, 'Should trigger multiple times');
    }
}
