<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities\Chains;

use Noem\State\Feature\Abilities\Chains\Params\ExecuteAbilityHandler as ExecuteParams;
use Noem\State\Middleware\Chain;

/**
 * Chain for executing ability handlers with middleware support
 *
 * Provides clean hook point for AsyncFeature to intercept generator execution.
 * Similar to InvokeCallback but for ability handlers.
 *
 * @template-extends Chain<ExecuteParams, mixed>
 */
class ExecuteAbilityHandler extends Chain
{
    public function __construct()
    {
        parent::__construct(function (ExecuteParams $params): mixed {
            // Terminal handler - execute the ability handler with parameters
            return ($params->handler)($params->parameters);
        });
    }
}
