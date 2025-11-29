<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Machine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Machine builderArgs method includes loader configuration
 */
#[Group('loader')]
#[Group('machine-abstraction')]
class MachineBuilderArgsTest extends TestCase
{
    public function testBuilderArgsIncludesLoaderConfiguration(): void
    {
        $yaml = 'states: [{name: test}]';
        
        $machine = new class($yaml) extends Machine {
            public function __construct(private string $yaml)
            {
            }
            
            public function yaml(): string
            {
                return $this->yaml;
            }
            
            public function trigger(): object
            {
                return new \stdClass();
            }
        };
        
        $builderArgs = $machine->builderArgs();
        
        $this->assertIsArray($builderArgs);
        $this->assertArrayHasKey('loader', $builderArgs);
        $this->assertArrayHasKey('yaml', $builderArgs['loader']);
        $this->assertArrayHasKey('yamlHelpers', $builderArgs['loader']);
        $this->assertSame($yaml, $builderArgs['loader']['yaml']);
        $this->assertIsArray($builderArgs['loader']['yamlHelpers']);
    }
}
