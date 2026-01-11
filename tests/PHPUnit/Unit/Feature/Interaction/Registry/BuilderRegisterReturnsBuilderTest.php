<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\BuildStep\RegisterInteraction;
use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegisterInteraction::class)]
class BuilderRegisterReturnsBuilderTest extends TestCase
{
    public function testAddBuildStepReturnsRegionBuilderInstanceForChaining(): void
    {
        $builder = new RegionBuilder();

        $definition = new InteractionDefinition(
            id: 'deploy_confirm',
            type: 'confirm',
            state: 'deploying',
            question: 'Deploy to production?',
            metadata: ['defaultValue' => false]
        );

        $result = $builder->addBuildStep(
            new RegisterInteraction('deploy_confirm', $definition)
        );

        $this->assertSame($builder, $result);
    }
}
