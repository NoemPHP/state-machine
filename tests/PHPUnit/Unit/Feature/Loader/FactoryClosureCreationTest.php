<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray creates factory closure from callable definition
 */
#[Group('loader')]
#[Group('guard-and-callback-creation')]
class FactoryClosureCreationTest extends TestCase
{
    public function testCreatesFactoryClosureFromCallable(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());
        
        $factoryDefinition = fn() => 'factory_result';
        
        $factory = $processor->createFactoryCallback($factoryDefinition);
        
        $this->assertInstanceOf(\Closure::class, $factory);
        $this->assertEquals('factory_result', $factory());
    }
}
