<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Runtime;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: StandardRuntime inherits all behavior from Runtime base class
 */
#[Group('runtime')]
#[Group('standard-runtime')]
class StandardRuntimeInheritsAllTest extends TestCase
{
    public function testStandardRuntimeExtendsRuntimeBaseClass(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->build();

        $runtime = new StandardRuntime($region);

        $this->assertInstanceOf(Runtime::class, $runtime, 'StandardRuntime should extend Runtime base class');
    }

    public function testStandardRuntimeHasNoAdditionalPublicMethods(): void
    {
        $reflection = new \ReflectionClass(StandardRuntime::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        // Get methods declared in StandardRuntime itself (not inherited)
        $ownMethods = array_filter($methods, function ($method) {
            return $method->getDeclaringClass()->getName() === StandardRuntime::class;
        });

        // StandardRuntime should have no public methods of its own
        // (everything comes from Runtime base class)
        $ownMethodNames = array_map(fn($m) => $m->getName(), $ownMethods);
        $ownMethodNames = array_filter($ownMethodNames, fn($name) => $name !== '__construct');

        $this->assertEmpty($ownMethodNames, 'StandardRuntime should not declare additional public methods beyond constructor');
    }

    public function testStandardRuntimeProvidesAllRuntimeMethods(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->build();

        $runtime = new StandardRuntime($region);

        // Verify all expected Runtime methods are available
        $this->assertTrue(method_exists($runtime, 'run'));
        $this->assertTrue(method_exists($runtime, 'events'));
        $this->assertTrue(method_exists($runtime, 'getIterator'));
        $this->assertTrue(method_exists($runtime, 'isComplete'));
        $this->assertTrue(method_exists($runtime, 'getRegion'));
        $this->assertTrue(method_exists($runtime, 'getConfig'));
        $this->assertTrue(method_exists($runtime, 'spawn'));
    }

    public function testStandardRuntimeCanExecuteRegions(): void
    {
        $executed = false;

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$executed): void {
                $executed = true;
            })
            ->build();

        $runtime = new StandardRuntime($region);

        $runtime->run();

        $this->assertTrue($executed, 'StandardRuntime should be able to execute regions');
        $this->assertTrue($runtime->isComplete());
    }

    public function testStandardRuntimeSupportsNonBlockingExecution(): void
    {
        $iterationCount = 0;

        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t) use (&$iterationCount): bool {
                $iterationCount++;
                return $iterationCount >= 3;
            }))
            ->build();

        $runtime = new StandardRuntime($region);

        // Step-by-step execution
        $runtime->run(steps: 1);
        $this->assertEquals(1, $iterationCount);

        $runtime->run(steps: 1);
        $this->assertEquals(2, $iterationCount);

        $runtime->run(steps: 1);
        $this->assertEquals(3, $iterationCount);
        $this->assertTrue($runtime->isComplete());
    }

    public function testStandardRuntimeSupportsEventStreaming(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'processing', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->onEnter('start', function (object $t) use (&$region) {
                $region->trigger((object)['marker' => 'test'], enqueue: true);
            })
            ->addBuildStep(new AddTransition('start', 'processing'))
            ->addBuildStep(new AddTransition('processing', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $events = [];
        foreach ($runtime->events() as $event) {
            if (isset($event->marker)) {
                $events[] = $event;
            }
        }

        $this->assertNotEmpty($events, 'StandardRuntime should support event streaming');
    }

    public function testStandardRuntimeCanSpawnChildRuntimes(): void
    {
        $parentRegion = (new RegionBuilder())
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $childRegion = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->build();

        $runtime = new StandardRuntime($parentRegion);

        $childRuntime = $runtime->spawn($childRegion);

        $this->assertInstanceOf(StandardRuntime::class, $childRuntime);
        $this->assertSame($childRegion, $childRuntime->getRegion());
    }
}
