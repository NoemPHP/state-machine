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
use Noem\State\Test\Helpers\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PresentationFeature::class)]
final class AcceptsRequiredParametersTest extends RegionBuilderTestCase
{
    public function testAcceptsKeyLabelIntentParameters(): void
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

        $builder->onEnter('idle', function (object $trigger) {
            $this->presentation('testKey', 'Test Label', 'Test Intent');
        });

        $region = $builder->setStates('idle')->build();
        $region->trigger((object)['type' => 'init']);

        $presentation = $registry->get('testKey');
        $this->assertNotNull($presentation);
        $this->assertSame('testKey', $presentation->key);
        $this->assertSame('Test Label', $presentation->label);
        $this->assertSame('Test Intent', $presentation->intent);
    }
}
