<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray creates default always-true guard when none specified
 */
#[Group('loader')]
#[Group('guard-and-callback-creation')]
class DefaultGuardCreationTest extends TestCase
{
    public function testCreatesDefaultAlwaysTrueGuard(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());

        // Transition without guard specified
        $transition = [
            'target' => 'nextState',
        ];

        $guard = $processor->createTransitionGuard($transition);

        $this->assertInstanceOf(\Closure::class, $guard);

        // Guard should always return true
        $trigger = (object)['test' => 'data'];
        $result = $guard($trigger);

        $this->assertTrue($result);
    }
}
