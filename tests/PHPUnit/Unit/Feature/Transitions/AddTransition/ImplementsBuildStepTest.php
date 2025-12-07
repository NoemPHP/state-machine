<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\AddTransition;

use Noem\State\BuildStep;
use Noem\State\Feature\Transitions\AddTransition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AddTransition implements BuildStep interface
 */
#[Group('transitions')]
#[Group('add-transition-buildstep')]
class ImplementsBuildStepTest extends TestCase
{
    public function testImplementsBuildStepInterface(): void
    {
        $addTransition = new AddTransition('from', 'to');

        $this->assertInstanceOf(BuildStep::class, $addTransition);
    }

    public function testHasCallbackMethod(): void
    {
        $addTransition = new AddTransition('from', 'to');

        $this->assertTrue(method_exists($addTransition, 'callback'));
    }

    public function testCallbackAcceptsCorrectParameters(): void
    {
        $addTransition = new AddTransition('from', 'to');
        $reflection = new \ReflectionMethod($addTransition, 'callback');

        $parameters = $reflection->getParameters();
        $this->assertCount(3, $parameters);

        $this->assertEquals('builder', $parameters[0]->getName());
        $this->assertEquals('next', $parameters[1]->getName());
        $this->assertEquals('first', $parameters[2]->getName());
    }
}
