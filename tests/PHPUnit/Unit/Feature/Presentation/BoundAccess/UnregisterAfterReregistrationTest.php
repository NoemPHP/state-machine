<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Presentation\BoundAccess;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PresentationFeature::class)]
class UnregisterAfterReregistrationTest extends TestCase
{
    public function testUnregisterCallableWorksAfterKeyReregistration(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new SubscriptionFeature(),
            new MessageFeature(),
            new ExtendedState(),
            new JsonSchemaFeature(),
            new AbilitiesFeature(),
            new PresentationFeature()
        );

        $builder->setStates('idle')->build();
        $registry = $builder->chainMail->get(PresentationRegistry::class);
        $registry->setSchemas(['testKey' => ['type' => 'string']]);

        $firstUnregister = null;
        $secondUnregister = null;

        $builder->onEnter('idle', function (object $trigger) use (&$firstUnregister, &$secondUnregister) {
            $firstUnregister = $this->presentation('testKey', 'Label1', 'Intent1');
            $secondUnregister = $this->presentation('testKey', 'Label2', 'Intent2');
        });

        $region = $builder->setStates('idle')->build();
        $region->trigger((object)['type' => 'init']);

        // Second registration overwrites first
        $presentation = $registry->get('testKey');
        $this->assertSame('Label2', $presentation->label);

        // First unregister should not remove (stale reference)
        $firstUnregister();
        $this->assertNotNull($registry->get('testKey'), 'Stale unregister should not affect new registration');

        // Second unregister should remove
        $secondUnregister();
        $this->assertNull($registry->get('testKey'), 'Current unregister should remove presentation');
    }
}
