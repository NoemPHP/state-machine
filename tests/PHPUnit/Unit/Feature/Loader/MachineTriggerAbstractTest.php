<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Machine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: Machine trigger method is abstract
 */
#[Group('loader')]
#[Group('machine-abstraction')]
class MachineTriggerAbstractTest extends TestCase
{
    public function testTriggerMethodIsAbstract(): void
    {
        $reflection = new ReflectionClass(Machine::class);

        $this->assertTrue(
            $reflection->isAbstract(),
            'Machine class should be abstract'
        );

        $method = $reflection->getMethod('trigger');

        $this->assertTrue(
            $method->isAbstract(),
            'trigger() method should be abstract'
        );

        $this->assertTrue(
            $method->isPublic(),
            'trigger() method should be public'
        );

        // Verify return type is object
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'trigger() should have a return type');
        $this->assertSame('object', $returnType->getName());
    }
}
