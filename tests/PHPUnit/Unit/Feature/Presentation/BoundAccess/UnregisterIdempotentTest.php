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
class UnregisterIdempotentTest extends TestCase
{
    public function testUnregisterCallableIsIdempotent(): void
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

        $unregisterCallable = null;

        $builder->onEnter('idle', function (object $trigger) use (&$unregisterCallable) {
            $unregisterCallable = $this->presentation('testKey', 'Label', 'Intent');
        });

        $region = $builder->setStates('idle')->build();
        $region->trigger((object)['type' => 'init']);

        // Call unregister multiple times - should not throw
        $unregisterCallable();
        $unregisterCallable();
        $unregisterCallable();

        // Still null after multiple calls
        $this->assertNull($registry->get('testKey'), 'Multiple unregister calls should be safe');
    }
}
