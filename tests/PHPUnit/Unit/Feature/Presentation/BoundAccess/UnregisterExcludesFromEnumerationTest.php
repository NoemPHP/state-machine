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
class UnregisterExcludesFromEnumerationTest extends TestCase
{
    public function testUnregisteredPresentationExcludedFromEnumeration(): void
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
        $registry->setSchemas([
            'key1' => ['type' => 'string'],
            'key2' => ['type' => 'string']
        ]);

        $unregisterCallable = null;

        $builder->onEnter('idle', function (object $trigger) use (&$unregisterCallable) {
            $this->presentation('key1', 'Label1', 'Intent1');
            $unregisterCallable = $this->presentation('key2', 'Label2', 'Intent2');
        });

        $region = $builder->setStates('idle')->build();
        $region->trigger((object)['type' => 'init']);

        // Verify both presentations registered
        $this->assertCount(2, $registry->all());

        // Unregister key2
        $unregisterCallable();

        // Verify only key1 in enumeration
        $all = $registry->all();
        $this->assertCount(1, $all);
        $this->assertArrayHasKey('key1', $all);
        $this->assertArrayNotHasKey('key2', $all);
    }
}
