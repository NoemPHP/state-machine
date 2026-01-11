<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry\Definition;

use Noem\State\Feature\Interaction\InteractionDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Interaction\InteractionDefinition
 */
class StoresQuestionTest extends TestCase
{
    public function testStoresQuestionAsReadonlyStringProperty(): void
    {
        $question = 'Are you sure you want to deploy?';
        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: $question
        );

        $this->assertSame($question, $definition->question);
    }

    public function testQuestionIsReadonly(): void
    {
        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        $reflection = new \ReflectionProperty($definition, 'question');
        $this->assertTrue($reflection->isReadOnly());
    }
}
