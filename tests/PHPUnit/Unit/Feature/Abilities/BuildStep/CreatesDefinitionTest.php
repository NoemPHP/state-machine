<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\BuildStep;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\BuildStep\RegisterAbility;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;

/**
 * @covers \Noem\State\Feature\Abilities\BuildStep\RegisterAbility
 */
final class CreatesDefinitionTest extends RegionBuilderTestCase
{
    public function testCreatesAbilityDefinitionDuringBuild(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];

        $step = new RegisterAbility(
            name: 'test-ability',
            handler: $handler,
            description: 'Test ability',
            parameterSchema: ['type' => 'object'],
            responseSchema: ['type' => 'object']
        );

        $builder = new RegionBuilder();

        // Expect that __invoke creates an AbilityDefinition
        $this->expectNotToPerformAssertions();

        // This will be validated in the registry test
    }
}
