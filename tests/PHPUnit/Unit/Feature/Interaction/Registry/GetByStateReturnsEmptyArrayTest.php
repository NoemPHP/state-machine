<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class GetByStateReturnsEmptyArrayTest extends TestCase
{
    public function testReturnsEmptyArrayWhenNoInteractionsMatchState(): void
    {
        $registry = new InteractionRegistry();

        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        $registry->register($definition);
        $interactions = $registry->getByState('non_matching_state');

        $this->assertIsArray($interactions);
        $this->assertEmpty($interactions);
    }

    public function testReturnsEmptyArrayForEmptyRegistry(): void
    {
        $registry = new InteractionRegistry();

        $interactions = $registry->getByState('any_state');

        $this->assertIsArray($interactions);
        $this->assertEmpty($interactions);
    }
}
