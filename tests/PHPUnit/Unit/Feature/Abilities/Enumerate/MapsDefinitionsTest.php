<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Enumerate;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\ConnectedRegions;
use Noem\State\Events;
use Noem\State\Chains\Notification;
use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: enumerate-abilities handler maps definitions to serializable array structure
 *
 * Intent: Converts AbilityDefinition objects to arrays containing name, description, schemas
 */
#[Group('abilities')]
#[Group('enumerate-abilities-builtin')]
class MapsDefinitionsTest extends TestCase
{
    #[Test]
    public function eachAbilityInResponseHasNameDescriptionAndSchemas(): void
    {
        $chainMail = new ChainMail();

        $chainMail->supply(
            fn(): DispatchAction => new DispatchAction(new ConnectedRegions(), new Events(new \Noem\State\Chains\ValidateCallback(), new \Noem\State\Chains\PrepareInvokable(), new \Noem\State\Chains\InvokeCallback(), new \Noem\State\Callbacks\CallbackRegistry())),
            fn(): Notification => new Notification(),
            fn(): AbilityRegistry => new AbilityRegistry()
        );

        $feature = new AbilitiesFeature();
        $feature($chainMail);

        $registry = $chainMail->get(AbilityRegistry::class);

        // Register a custom ability for testing
        $customAbility = new AbilityDefinition(
            name: 'test-ability',
            description: 'A test ability with parameters',
            parameterSchema: [
                'type' => 'object',
                'properties' => [
                    'param1' => ['type' => 'string'],
                ],
            ],
            responseSchema: [
                'type' => 'object',
                'properties' => [
                    'result' => ['type' => 'string'],
                ],
            ],
            handler: fn($params) => ['result' => 'test']
        );
        $registry->register($customAbility);

        // Get the enumerate-abilities ability
        $enumerateAbility = $registry->get('enumerate-abilities');
        $handler = $enumerateAbility->handler;

        // Execute the handler
        $result = $handler(null);
        $abilities = $result['abilities'];

        // Find the test ability in results
        $testAbility = null;
        foreach ($abilities as $ability) {
            if ($ability['name'] === 'test-ability') {
                $testAbility = $ability;
                break;
            }
        }

        $this->assertNotNull(
            $testAbility,
            'test-ability should be in enumeration results'
        );

        // Verify all required fields are present
        $this->assertArrayHasKey(
            'name',
            $testAbility,
            'Each ability should have name property'
        );

        $this->assertArrayHasKey(
            'description',
            $testAbility,
            'Each ability should have description property'
        );

        $this->assertArrayHasKey(
            'parameterSchema',
            $testAbility,
            'Each ability should have parameterSchema property'
        );

        $this->assertArrayHasKey(
            'responseSchema',
            $testAbility,
            'Each ability should have responseSchema property'
        );

        // Verify the values match the original definition
        $this->assertSame('test-ability', $testAbility['name']);
        $this->assertSame('A test ability with parameters', $testAbility['description']);
        $this->assertSame($customAbility->parameterSchema, $testAbility['parameterSchema']);
        $this->assertSame($customAbility->responseSchema, $testAbility['responseSchema']);
    }
}
