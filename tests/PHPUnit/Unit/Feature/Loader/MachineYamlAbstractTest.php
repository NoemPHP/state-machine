<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Machine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: Machine yaml method is abstract
 */
#[Group('loader')]
#[Group('machine-abstraction')]
class MachineYamlAbstractTest extends TestCase
{
    public function testYamlMethodIsAbstract(): void
    {
        $reflection = new ReflectionClass(Machine::class);

        $this->assertTrue(
            $reflection->isAbstract(),
            'Machine class should be abstract'
        );

        $method = $reflection->getMethod('yaml');

        $this->assertTrue(
            $method->isAbstract(),
            'yaml() method should be abstract'
        );

        $this->assertTrue(
            $method->isPublic(),
            'yaml() method should be public'
        );

        // Verify return type is string
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'yaml() should have a return type');
        $this->assertSame('string', $returnType->getName());
    }
}
