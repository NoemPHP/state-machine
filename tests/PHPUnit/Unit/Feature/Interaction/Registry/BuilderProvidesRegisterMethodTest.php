<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\BuildStep\RegisterInteraction;
use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegisterInteraction::class)]
class BuilderProvidesRegisterMethodTest extends TestCase
{
    public function testRegionBuilderProvidesAddBuildStepForInteractionRegistration(): void
    {
        $builder = new RegionBuilder();

        $definition = new InteractionDefinition(
            id: 'deploy_confirm',
            type: 'confirm',
            state: 'deploying',
            question: 'Deploy to production?',
            metadata: ['defaultValue' => false]
        );

        // Verify BuildStep can be added (provides registration mechanism)
        $result = $builder->addBuildStep(
            new RegisterInteraction('deploy_confirm', $definition)
        );

        $this->assertInstanceOf(RegionBuilder::class, $result);
    }
}