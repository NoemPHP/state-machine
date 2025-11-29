<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Machine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Machine container method returns empty iterable by default
 */
#[Group('loader')]
#[Group('machine-abstraction')]
class MachineDefaultContainerTest extends TestCase
{
    public function testContainerReturnsEmptyIterableByDefault(): void
    {
        $machine = new class extends Machine {
            public function yaml(): string
            {
                return 'states: [{name: test}]';
            }
            
            public function trigger(): object
            {
                return new \stdClass();
            }
        };
        
        $container = $machine->container();
        
        $this->assertIsIterable($container);
        $this->assertCount(0, iterator_to_array($container));
    }
}
