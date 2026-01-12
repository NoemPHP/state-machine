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
class ImmediatelyAvailableTest extends TestCase
{
    public function testPresentationImmediatelyAvailableForDiscovery(): void
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

        $builder->onEnter('idle', function (object $trigger) use ($registry) {
            $this->presentation('testKey', 'Label', 'Intent');

            // Verify immediately available in same callback
            $presentation = $registry->get('testKey');
            $this->set('wasAvailable', $presentation !== null);
        });

        $region = $builder->setStates('idle')->build();
        $region->trigger((object)['type' => 'init']);

        // Access via abilities instead of direct region->get()
        $presentation = $registry->get('testKey');
        $this->assertNotNull($presentation, 'Presentation must be immediately available after registration');
    }
}
