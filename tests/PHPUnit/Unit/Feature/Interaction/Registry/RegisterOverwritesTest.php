<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class RegisterOverwritesTest extends TestCase
{
    public function testRegisterOverwritesExistingInteractionWithSameId(): void
    {
        $registry = new InteractionRegistry();

        $definition1 = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy to production?'
        );
        $definition2 = new InteractionDefinition(
            id: 'confirm_deploy',  // Same ID
            type: 'confirm',
            state: 'ready',
            question: 'Really deploy?' // Different question
        );

        $registry->register($definition1);
        $registry->register($definition2);

        $retrieved = $registry->get('confirm_deploy');

        $this->assertSame($definition2, $retrieved);
        $this->assertNotSame($definition1, $retrieved);
    }

    public function testOverwriteDoesNotIncreaseCount(): void
    {
        $registry = new InteractionRegistry();

        $definition1 = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );
        $definition2 = new InteractionDefinition(
            id: 'confirm_deploy',  // Same ID
            type: 'confirm',
            state: 'ready',
            question: 'Really deploy?'
        );

        $registry->register($definition1);
        $registry->register($definition2);

        $all = $registry->all();
        $this->assertCount(1, $all);
    }
}
