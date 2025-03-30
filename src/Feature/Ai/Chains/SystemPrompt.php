<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai\Chains;

use Noem\State\Feature\Ai\Chains\Params\SystemPromptParams;
use Noem\State\Middleware\Chain;

/**
 * @template-extends Chain<SystemPromptParams,string>
 */
class SystemPrompt extends Chain
{
}
