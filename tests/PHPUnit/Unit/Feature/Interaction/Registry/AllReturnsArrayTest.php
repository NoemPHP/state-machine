<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class AllReturnsArrayTest extends TestCase
{
    public function testReturnsArrayOfAllRegisteredInteractionDefinitions(): void
    {
        $registry = new InteractionRegistry();

        $definition1 = new InteractionDefinition(
            id: 'interaction_1',
            type: 'confirm',
            state: 'state1',
            question: 'Question 1?'
        );
        $definition2 = new InteractionDefinition(
            id: 'interaction_2',
            type: 'select',
            state: 'state2',
            question: 'Question 2?'
        );
        $definition3 = new InteractionDefinition(
            id: 'interaction_3',
            type: 'choice',
            state: 'state3',
            question: 'Question 3?'
        );

        $registry->register($definition1);
        $registry->register($definition2);
        $registry->register($definition3);

        $all = $registry->all();

        $this->assertIsArray($all);
        $this->assertCount(3, $all);
        $this->assertContains($definition1, $all);
        $this->assertContains($definition2, $all);
        $this->assertContains($definition3, $all);
    }

    public function testReturnsEmptyArrayForEmptyRegistry(): void
    {
        $registry = new InteractionRegistry();

        $all = $registry->all();

        $this->assertIsArray($all);
        $this->assertEmpty($all);
    }

    public function testAllMethodExists(): void
    {
        $this->assertTrue(method_exists(InteractionRegistry::class, 'all'));
    }
}
