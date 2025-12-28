<?php

declare(strict_types=1);

namespace Noem\State\Feature\Agentic;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Agentic\Chains\Weave;
use Noem\State\Feature\Agentic\Chains\Params\Weave as WeaveParams;
use Noem\State\Feature\Agentic\WeaveConfig;
use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\ExtendedState\ContextChains\Params\BoundAccessParams;
use Noem\State\Feature\Feature;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;

/**
 * Agentic Feature - AI-powered autonomous tool orchestration
 *
 * Provides Weave chain for intent-driven multi-step workflows where AI:
 * 1. Discovers available tools (via AbilitiesFeature)
 * 2. Selects appropriate tools based on natural language intent
 * 3. Executes selected tools sequentially
 * 4. Synthesizes final result from tool outputs
 *
 * Dependencies (required):
 * - AbilitiesFeature: Tool enumeration and invocation
 * - AiFeature: AI-powered planning and aggregation via capture()/complete()
 *
 * Dependencies (optional):
 * - ExtendedState: Enables $this->weave() context API via BoundAccess
 *
 * The Weave chain is always available via ChainMail, even without ExtendedState.
 */
#[RequiresFeature(AbilitiesFeature::class)]
#[RequiresFeature(AiFeature::class)]
class AgenticFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        // Register Weave chain in ChainMail (always available, even without ExtendedState)
        // WeaveConfig is optional - supplied by AiConfigFeature if configured
        $chainMail->supply(
            fn(InvokeAbility $invokeAbility, Mesh $aiBackends, AbilityRegistry $registry, ?WeaveConfig $weaveConfig = null): Weave =>
                new Weave($invokeAbility, $aiBackends, $registry, $weaveConfig)
        );

        // Optionally wire weave() method into BoundAccess if ExtendedState is loaded
        $chainMail->use(
            function (?BoundAccess $boundAccess = null, ?Weave $weaveChain = null) {
                if ($boundAccess !== null && $weaveChain !== null) {
                    $this->registerWeaveMethod($boundAccess, $weaveChain);
                }
            }
        );
    }

    /**
     * Register weave() method in BoundAccess chain
     */
    private function registerWeaveMethod(BoundAccess $boundAccess, Weave $weaveChain): void
    {
        $boundAccess->link(function (BoundAccessParams $params, callable $next) use ($weaveChain) {
            // Only intercept weave() method calls
            if ($params->type !== BoundAccessParams::TYPE_METHOD || $params->name !== 'weave') {
                return $next($params);
            }

            // Extract arguments: weave($intent, $options = [])
            $args = $params->payload ?? [];
            $intent = $args[0] ?? '';
            $options = $args[1] ?? [];

            // Create params and invoke Weave chain
            $weaveParams = new WeaveParams(
                region: $params->region,
                intent: $intent,
                options: $options
            );

            // Execute via chain (returns Generator)
            return $weaveChain->call($weaveParams);
        });
    }
}
