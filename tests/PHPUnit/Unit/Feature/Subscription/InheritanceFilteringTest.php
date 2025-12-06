<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Subscription;

use Noem\State\Chains\Params\Notify;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class BaseEvent
{
    public string $type = 'base';
}

class DerivedEvent extends BaseEvent
{
    public string $extra = 'derived';
}

interface EventInterface
{
}

class InterfaceEvent implements EventInterface
{
    public string $data = 'interface';
}

/**
 * Acceptance Criterion: Type filtering supports inheritance and interface implementation
 *
 * @see specs/features/subscription.yaml
 */
#[Group('feature')]
#[Group('subscription')]
class InheritanceFilteringTest extends TestCase
{
    public function testBaseTypeListenerReceivesDerivedEvents(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(new SubscriptionFeature())
            ->setStates('idle')
            ->build();

        $baseCalled = false;

        $region->on(function (BaseEvent $e) use (&$baseCalled) {
            $baseCalled = true;
        });

        // Act - emit derived event
        $listeners = $region->notificationChain->call(new Notify($region, new DerivedEvent()));
        foreach ($listeners as $listener) {
            $listener(new DerivedEvent(), $region);
        }

        // Assert - base type listener receives derived event
        $this->assertTrue($baseCalled);
    }

    public function testInterfaceTypeListenerReceivesImplementations(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(new SubscriptionFeature())
            ->setStates('idle')
            ->build();

        $interfaceCalled = false;

        $region->on(function (EventInterface $e) use (&$interfaceCalled) {
            $interfaceCalled = true;
        });

        // Act
        $listeners = $region->notificationChain->call(new Notify($region, new InterfaceEvent()));
        foreach ($listeners as $listener) {
            $listener(new InterfaceEvent(), $region);
        }

        // Assert
        $this->assertTrue($interfaceCalled);
    }

    public function testDerivedTypeListenerDoesNotReceiveBaseEvents(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(new SubscriptionFeature())
            ->setStates('idle')
            ->build();

        $derivedListener = fn(DerivedEvent $e) => 'derived';

        $region->on($derivedListener);

        // Act - emit base event
        $listeners = $region->notificationChain->call(new Notify($region, new BaseEvent()));

        // Assert - derived listener NOT returned
        $this->assertNotContains($derivedListener, $listeners);
    }
}
