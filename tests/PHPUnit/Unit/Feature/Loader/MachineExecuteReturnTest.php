<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Machine;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Machine execute method returns last trigger result
 */
#[Group('loader')]
#[Group('machine-abstraction')]
class MachineExecuteReturnTest extends TestCase
{
    public function testExecuteReturnsLastTriggerResult(): void
    {
        $triggerCount = 0;

        $machine = new class ($triggerCount) extends Machine {
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
                $trigger->label = "trigger-{$this->count}";
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

        // Result should be the last trigger object
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('id', $result);
        $this->assertObjectHasProperty('label', $result);

        // Should be the last trigger that completed the state machine
        $this->assertSame($triggerCount, $result->id);
        $this->assertSame("trigger-{$triggerCount}", $result->label);

        // Verify the region reached final state
        $this->assertTrue($region->isFinal());
    }
}
