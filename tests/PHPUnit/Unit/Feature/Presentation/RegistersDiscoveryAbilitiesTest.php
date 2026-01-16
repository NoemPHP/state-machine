<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationFeature registers discovery abilities in AbilityRegistry
 * Intent: Exposes enumerate-presentations and get-presented-state abilities for introspection
 * Criticality: contract
 */
final class RegistersDiscoveryAbilitiesTest extends TestCase
{
    public function testEnumeratePresentationsAbilityRegistered(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new ExtendedState(),
            new JsonSchemaFeature(),
            new AbilitiesFeature(),
            new PresentationFeature()
        );

        $builder->addState('initial');
        $region = $builder->build();

        // Access AbilityRegistry from RegionBuilder's ChainMail
        $registry = $builder->chainMail->get(AbilityRegistry::class);

        // enumerate-presentations ability should be registered
        $ability = $registry->get('enumerate-presentations');

        $this->assertNotNull($ability);
        $this->assertSame('enumerate-presentations', $ability->name);
    }

    public function testGetPresentedStateAbilityRegistered(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new ExtendedState(),
            new JsonSchemaFeature(),
            new AbilitiesFeature(),
            new PresentationFeature()
        );

        $builder->addState('initial');
        $region = $builder->build();

        // Access AbilityRegistry from RegionBuilder's ChainMail
        $registry = $builder->chainMail->get(AbilityRegistry::class);

        // get-presented-state ability should be registered
        $ability = $registry->get('get-presented-state');

        $this->assertNotNull($ability);
        $this->assertSame('get-presented-state', $ability->name);
    }
}
