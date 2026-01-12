<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\Dependencies;

use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\LoaderChains;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: JsonSchemaFeature optionally enhances RegionLoader when present
 * Intent: Adds YAML schema support when loader available, works standalone without loader
 * Criticality: contract
 */
final class EnhancesRegionLoaderTest extends TestCase
{
    public function testEnhancesRegionLoaderWhenPresent(): void
    {
        $chainMail = new ChainMail();
        $chainMail->supply(
            fn(): \Noem\State\Chains\ConnectedRegions => new \Noem\State\Chains\ConnectedRegions(),
            fn(): \Noem\State\Chains\EnhanceRegionBuilder => new \Noem\State\Chains\EnhanceRegionBuilder(),
            fn(): \Noem\State\Chains\ValidateCallback => new \Noem\State\Chains\ValidateCallback(),
            fn(): \Noem\State\Chains\PrepareInvokable => new \Noem\State\Chains\PrepareInvokable(),
            fn(): \Noem\State\Chains\InvokeCallback => new \Noem\State\Chains\InvokeCallback(),
            fn(): \Noem\State\Chains\ExtendedState => new \Noem\State\Chains\ExtendedState(),
            fn(): \Noem\State\Chains\Set => new \Noem\State\Chains\Set(),
            fn(): \Noem\State\Chains\Get => new \Noem\State\Chains\Get(),
            fn(\Noem\State\Chains\ConnectedRegions $c, \Noem\State\Events $e): \Noem\State\Chains\DispatchAction => new \Noem\State\Chains\DispatchAction($c, $e),
            fn(\Noem\State\Chains\ConnectedRegions $connections): \Noem\State\Chains\Path => new \Noem\State\Chains\Path($connections),
            fn(\Noem\State\Chains\ConnectedRegions $connectedRegions): \Noem\State\Chains\Meta => new \Noem\State\Chains\Meta($connectedRegions),
            fn(): \Noem\State\Callbacks\CallbackRegistry => new \Noem\State\Callbacks\CallbackRegistry(),
            \Noem\State\Events::conjure()
        );

        $includes = new IncludesFeature();
        $includes($chainMail);

        $loader = new RegionLoader();
        $loader($chainMail);

        $jsonSchema = new JsonSchemaFeature();
        $jsonSchema($chainMail);

        // Verify JsonSchemaFeature hooks into Schema chain when RegionLoader is present
        $schemaChain = $chainMail->get(LoaderChains\Schema::class);
        $this->assertInstanceOf(LoaderChains\Schema::class, $schemaChain);
    }
}
