<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\BuildStep\RegisterInteraction;
use Noem\State\Feature\Interaction\InteractionDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegisterInteraction::class)]
class BuilderRegisterAcceptsParametersTest extends TestCase
{
    public function testRegisterInteractionAcceptsStringIdAndInteractionDefinitionParameters(): void
    {
        $id = 'deploy_confirm';
        $definition = new InteractionDefinition(
            id: $id,
            type: 'confirm',
            state: 'deploying',
            question: 'Deploy to production?',
            metadata: ['defaultValue' => false]
        );

        $buildStep = new RegisterInteraction($id, $definition);

        $this->assertInstanceOf(RegisterInteraction::class, $buildStep);
    }
}