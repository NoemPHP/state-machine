<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Machine;
use Noem\State\Feature\Transitions\TransitionsFeature;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Machine run static method builds region with machine features and args
 */
#[Group('loader')]
#[Group('machine-abstraction')]
class MachineRunTest extends TestCase
{
    public function testRunBuildsRegionWithMachineFeaturesAndArgs(): void
    {
        $triggerCount = 0;
        $featuresUsed = false;
        
        $machine = new class($triggerCount, $featuresUsed) extends Machine {
            public function __construct(
                private int &$count,
                private bool &$featuresUsed
            ) {
            }
            
            public function yaml(): string
            {
                return <<<YAML
                states:
                  - name: start
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
                $this->featuresUsed = true;
                return [
                    parent::features()[0],
                    new TransitionsFeature(),
                ];
            }
        };
        
        // Use the static run method
        $result = Machine::run($machine);
        
        // Verify features were used
        $this->assertTrue($featuresUsed, 'Machine features should be used');
        
        // Verify triggers were called (at least once)
        $this->assertGreaterThanOrEqual(1, $triggerCount, 'Triggers should be called');
        
        // Verify result is returned
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('id', $result);
    }
}
