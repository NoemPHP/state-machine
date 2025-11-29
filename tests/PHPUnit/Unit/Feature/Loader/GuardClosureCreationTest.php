<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray creates guard closure from callable definition
 */
#[Group('loader')]
#[Group('guard-and-callback-creation')]
class GuardClosureCreationTest extends TestCase
{
    public function testCreatesGuardClosureFromCallable(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());
        
        $guardCallable = fn(object $t): bool => $t->value > 5;
        $transition = [
            'target' => 'nextState',
            'guard' => $guardCallable,
        ];
        
        $guard = $processor->createTransitionGuard($transition);
        
        $this->assertInstanceOf(\Closure::class, $guard);
        
        // Test guard logic
        $this->assertTrue($guard((object)['value' => 10]));
        $this->assertFalse($guard((object)['value' => 3]));
    }
}
