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
class AcceptsMetadataParameterTest extends TestCase
{
    public function testAcceptsOptionalMetadataParameter(): void
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

        $metadata = ['format' => 'email', 'hint' => 'User email address'];

        $builder->onEnter('idle', function (object $trigger) use ($metadata) {
            $this->presentation('testKey', 'Email', 'User contact', $metadata);
        });

        $region = $builder->setStates('idle')->build();
        $region->trigger((object)['type' => 'init']);

        $presentation = $registry->get('testKey');
        $this->assertNotNull($presentation);
        $this->assertSame($metadata, $presentation->metadata);
    }
}
