<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Machine;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Machine execute method uses region method result when no region provided
 */
#[Group('loader')]
#[Group('machine-abstraction')]
class MachineExecuteDefaultRegionTest extends TestCase
{
    public function testExecuteUsesRegionMethodWhenNoRegionProvided(): void
    {
        $regionMethodCalled = false;
        
        $machine = new class($regionMethodCalled) extends Machine {
            public function __construct(private bool &$called)
            {
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
                return new \stdClass();
            }
            
            public function features(): iterable
            {
                return [
                    parent::features()[0],
                    new TransitionsFeature(),
                ];
            }
            
            public function region(): Region
            {
                $this->called = true;
                $builder = new RegionBuilder();
                return $builder
                    ->enableFeatures(...$this->features())
                    ->build($this->builderArgs());
            }
        };
        
        // Call execute without providing a region
        $result = $machine->execute();
        
        $this->assertTrue($regionMethodCalled, 'region() method should be called when no region provided');
        $this->assertIsObject($result);
    }
}
