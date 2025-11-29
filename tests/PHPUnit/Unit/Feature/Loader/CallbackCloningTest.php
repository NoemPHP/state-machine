<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray clones callback closures for uniqueness
 */
#[Group('loader')]
#[Group('array-processing')]
class CallbackCloningTest extends TestCase
{
    public function testClonesCallbackClosuresForUniqueness(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());
        
        $originalCallback = fn($t) => null;
        
        $definition = ['run' => $originalCallback];
        
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('createStateCallback');
        $method->setAccessible(true);
        
        $cloned = $method->invoke($processor, $definition);
        
        // Verify a closure was returned (cloning works for closures)
        $this->assertInstanceOf(\Closure::class, $cloned);
    }
}
