<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Presentation\BoundAccess;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PresentationFeature::class)]
class RequiresPresentationFeatureTest extends TestCase
{
    public function testThrowsExceptionWhenPresentationFeatureNotLoaded(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new SubscriptionFeature(),
            new MessageFeature(),
            new ExtendedState(),
            new JsonSchemaFeature()
            // Note: PresentationFeature NOT loaded
        );

        $builder->onEnter('idle', function (object $trigger) {
            $this->presentation('testKey', 'Label', 'Intent');
        });

        $region = $builder->setStates('idle')->build();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Method 'presentation' not found in callback context");
        $region->trigger((object)['type' => 'init']);
    }
}
