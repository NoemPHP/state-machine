<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities\Chains;

use Noem\State\Chains\Params\Callback;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Feature\Abilities\Chains\Params\ExecuteAbilityHandler as ExecuteParams;
use Noem\State\Middleware\Chain;

/**
 * Chain for executing ability handlers with middleware support
 *
 * Provides clean hook point for AsyncFeature to intercept generator execution.
 * Similar to InvokeCallback but for ability handlers.
 * Binds handlers to Region's Bound context if ExtendedState is loaded.
 *
 * @template-extends Chain<ExecuteParams, mixed>
 */
class ExecuteAbilityHandler extends Chain
{
    public function __construct(
        private readonly ?PrepareInvokable $prepareInvokable = null
    ) {
        parent::__construct(function (ExecuteParams $params): mixed {
            // Bind handler to Region's Bound context if ExtendedState loaded
            $handler = $params->handler;

            if ($this->prepareInvokable !== null && $handler instanceof \Closure) {
                // Use PrepareInvokable chain to bind handler (ExtendedState hooks this)
                // Create dummy trigger - abilities don't have real triggers
                $dummyTrigger = new \stdClass();

                $callbackParams = new Callback(
                    region: $params->region,
                    handler: $handler,
                    trigger: $dummyTrigger,
                );
                // PrepareInvokable returns the handler itself, not the Callback params object
                $boundHandler = $this->prepareInvokable->call($callbackParams);

                // Only use bound handler if it's callable
                if ($boundHandler !== null && is_callable($boundHandler)) {
                    $handler = $boundHandler;
                }
            }

            // Execute (now bound if ExtendedState loaded)
            return $handler($params->parameters);
        });
    }
}
