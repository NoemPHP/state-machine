<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray creates state callback closure from run definition
 */
#[Group('loader')]
#[Group('guard-and-callback-creation')]
class StateCallbackCreationTest extends TestCase
{
    public function testCreatesStateCallbackFromRunDefinition(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());
        
        $called = false;
        $definition = [
            'run' => function(object $t) use (&$called) {
                $called = true;
                return $t->value * 2;
            }
        ];
        
        $callback = $processor->createStateCallback($definition);
        
        $this->assertInstanceOf(\Closure::class, $callback);
        
        $result = $callback((object)['value' => 5]);
        $this->assertTrue($called);
        $this->assertEquals(10, $result);
    }
}
