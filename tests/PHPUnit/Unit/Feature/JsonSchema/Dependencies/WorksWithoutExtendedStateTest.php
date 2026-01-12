<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\Dependencies;

use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: JsonSchemaFeature works without ExtendedState for validation-only usage
 * Intent: Supports validation scenarios where context storage not required
 * Criticality: constraint
 */
final class WorksWithoutExtendedStateTest extends TestCase
{
    public function testWorksWithoutExtendedState(): void
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

        // Note: ExtendedState chains NOT supplied
        // JsonSchemaFeature should still load for validation-only scenarios

        $jsonSchema = new JsonSchemaFeature();

        // Should not throw - feature gracefully handles missing ExtendedState
        $jsonSchema($chainMail);

        $this->assertTrue(true, 'JsonSchemaFeature loads successfully without ExtendedState');
    }
}
