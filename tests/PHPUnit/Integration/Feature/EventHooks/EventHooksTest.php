<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\EventHooks;

use Noem\State\Feature\EventHooks\EventHooks;
use Noem\State\Feature\EventHooks\Hook\After;
use Noem\State\Feature\EventHooks\Hook\Before;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\NamedEvents\Event;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

class EventHooksTest extends RegionBuilderTestCase
{
    /**
     * @return void
     */
    #[Test]
    #[TestDox('It executes even hooks in the correct order')]
    public function afterEvent()
    {
        $helloWorld = '';

        $this->builder
            ->enableFeatures(
                new EventHooks(),
                new ExtendedState()
            )
            ->setStates('one', 'two')
            ->onAction('one', #[After] function (Event $t) use (&$helloWorld) {
                $helloWorld .= ' world';
            })
            ->onAction('one', #[Before] function (Event $t) use (&$helloWorld) {
                $helloWorld .= 'hello';
            });
        $region = $this->builder->build();

        $event = new class implements Event {
            public function name(): string
            {
                return 'hello-world';
            }
        };

        $region->trigger($event);
        $this->assertSame(
            'hello world',
            $helloWorld,
            "Event hooks should have been called in the correct order"
        );
    }

    #[Test]
    #[TestDox('It correctly handles payload types when inspecting hook signatures')]
    public function ignoresNonMatchingEventType()
    {
        $helloWorld = 'hello';

        $this->builder
            ->enableFeatures(
                new EventHooks(),
                new ExtendedState()
            )
            ->setStates('one', 'two')
            ->onAction('one', #[After] function (Event $t) use (&$helloWorld) {
                $helloWorld .= ' world';
            })
            ->build();

        $region = $this->builder->build();
        $region->trigger((object)['foo' => 1]);
        $this->assertSame(
            'hello',
            $helloWorld
        );
    }

    /**
     * @return void
     */
    #[Test]
    public function hooksCanCauseTransitions()
    {
        $guardSpy = \Mockery::spy(fn() => true);
        $actual = '';

        $this->builder
            ->enableFeatures(
                new EventHooks(),
                new ExtendedState()
            )
            ->setStates('one', 'two', 'three')
            /**
             * We register a transition caused by an After event hook
             */
            ->addBuildStep(new AddTransition('one', 'two', #[After] fn(Event $t): bool => $guardSpy()))
            ->onAction('two', #[After] function (Event $t) use (&$actual) {
                $actual .= '/after';
            })
            ->onAction('two', #[Before] function (Event $t) use (&$actual) {
                $actual .= '/before';
            });
        $region = $this->builder->build();
        /**
         * We send a bogus trigger, not expecting a transition
         */
        $region->trigger((object)['foo' => 1]);
        $this->assertFalse(
            $region->isInState('two'),
            "Region should ignore non-matching event'"
        );

        $event = new class implements Event {
            public function name(): string
            {
                return 'hello-world';
            }
        };
        /**
         * We send a correct Event payload, expecting its After hook to cause
         * a transition. Therefore, we expect the Before action callback to stay silent
         */
        $region->trigger($event);
        /**
         * Send the event again. This time we expect both Before and After hooks to fire
         */
        $region->trigger($event);
        $guardSpy->shouldHaveBeenCalled()->once();
        $this->assertTrue($region->isInState('two'), "Region should be in state 'two'");
        $this->assertSame(
            '/after/before/after',
            $actual,
            "Event hooks should have been called in the correct order"
        );
    }
}
