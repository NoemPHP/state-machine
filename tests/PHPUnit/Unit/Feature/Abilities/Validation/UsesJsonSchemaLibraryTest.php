<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Abilities\Validation;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\BuildStep\RegisterAbility;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: AbilitiesFeature uses justinrainbow/json-schema for parameter validation
 * Intent: Leverages existing validation library for abilities parameter schema validation
 * Criticality: contract
 */
final class UsesJsonSchemaLibraryTest extends TestCase
{
    public function testAbilitiesUseJsonSchemaLibrary(): void
    {
        // Verify that JsonSchema\Validator is used by checking the namespace
        $this->assertTrue(
            class_exists('JsonSchema\Validator'),
            'justinrainbow/json-schema library must be available'
        );

        // Further verification: check that InvokeAbility uses the Validator class
        $reflectionClass = new \ReflectionClass(\Noem\State\Feature\Abilities\Chains\InvokeAbility::class);
        $source = file_get_contents($reflectionClass->getFileName());

        $this->assertStringContainsString(
            'use JsonSchema\Validator',
            $source,
            'InvokeAbility must import JsonSchema\Validator'
        );

        $this->assertStringContainsString(
            'new Validator()',
            $source,
            'InvokeAbility must instantiate Validator'
        );
    }
}
