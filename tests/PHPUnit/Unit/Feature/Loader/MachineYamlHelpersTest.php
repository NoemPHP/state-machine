<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Helper\ContainerGetHelper;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\Machine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Machine yamlHelpers method returns php and get helpers
 */
#[Group('loader')]
#[Group('machine-abstraction')]
class MachineYamlHelpersTest extends TestCase
{
    public function testYamlHelpersReturnsPhpAndGetHelpers(): void
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

        $helpers = $machine->yamlHelpers();

        $this->assertIsArray($helpers);
        $this->assertArrayHasKey('php', $helpers);
        $this->assertArrayHasKey('get', $helpers);
        $this->assertInstanceOf(PhpEvalHelper::class, $helpers['php']);
        $this->assertInstanceOf(ContainerGetHelper::class, $helpers['get']);
    }
}
