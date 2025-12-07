<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Machine;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Machine execute method accepts optional region parameter
 */
#[Group('loader')]
#[Group('machine-abstraction')]
class MachineExecuteWithRegionTest extends TestCase
{
    public function testExecuteAcceptsOptionalRegionParameter(): void
    {
        $machine = new class extends Machine {
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
                return new \stdClass();
            }

            public function features(): iterable
            {
                return [
                    parent::features()[0],
                    new TransitionsFeature(),
                ];
            }
        };

        // Build a region externally
        $builder = new RegionBuilder();
        $externalRegion = $builder
            ->enableFeatures(...$machine->features())
            ->build($machine->builderArgs());

        $this->assertFalse($externalRegion->isFinal());

        // Pass the external region to execute
        $result = $machine->execute($externalRegion);

        $this->assertTrue($externalRegion->isFinal());
        $this->assertIsObject($result);
    }
}
