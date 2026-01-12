<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\Dependencies;

use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: JsonSchemaFeature works without RegionLoader for programmatic usage
 * Intent: Supports non-YAML workflows where schemas defined programmatically
 * Criticality: constraint
 */
final class WorksWithoutRegionLoaderTest extends TestCase
{
    public function testWorksWithoutRegionLoader(): void
    {
        $chainMail = new ChainMail();
        $chainMail->supply(
            fn(): \Noem\State\Chains\ConnectedRegions => new \Noem\State\Chains\ConnectedRegions(),
            fn(): \Noem\State\Chains\EnhanceRegionBuilder => new \Noem\State\Chains\EnhanceRegionBuilder(),
            fn(): \Noem\State\Chains\ValidateCallback => new \Noem\State\Chains\ValidateCallback(),
            fn(): \Noem\State\Chains\PrepareInvokable => new \Noem\State\Chains\PrepareInvokable(),
            fn(): \Noem\State\Chains\InvokeCallback => new \Noem\State\Chains\InvokeCallback(),
            fn(\Noem\State\Chains\ConnectedRegions $c, \Noem\State\Events $e): \Noem\State\Chains\DispatchAction => new \Noem\State\Chains\DispatchAction($c, $e),
            fn(\Noem\State\Chains\ConnectedRegions $connections): \Noem\State\Chains\Path => new \Noem\State\Chains\Path($connections),
            fn(): \Noem\State\Callbacks\CallbackRegistry => new \Noem\State\Callbacks\CallbackRegistry(),
            \Noem\State\Events::conjure()
        );

        // Note: RegionLoader chains NOT supplied (no LoaderChains\Schema)
        // JsonSchemaFeature should still load for programmatic usage

        $jsonSchema = new JsonSchemaFeature();

        // Should not throw - feature gracefully handles missing RegionLoader
        $jsonSchema($chainMail);

        $this->assertTrue(true, 'JsonSchemaFeature loads successfully without RegionLoader');
    }
}
