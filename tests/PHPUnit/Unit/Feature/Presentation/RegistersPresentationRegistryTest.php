<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationFeature registers PresentationRegistry Mesh in ChainMail
 * Intent: Provides centralized presentation storage through PresentationRegistry Mesh registration
 * Criticality: contract
 */
final class RegistersPresentationRegistryTest extends TestCase
{
    public function testPresentationRegistryAvailableInChainMail(): void
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

        // Access ChainMail from RegionBuilder
        $chainMail = $builder->chainMail;

        // PresentationRegistry should be registered in ChainMail
        $registry = $chainMail->get(PresentationRegistry::class);

        $this->assertInstanceOf(PresentationRegistry::class, $registry);
    }
}
