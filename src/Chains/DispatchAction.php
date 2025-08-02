<?php

namespace Noem\State\Chains;

use Noem\State\Chains\Params\Action;
use Noem\State\Middleware\Chain;

/**
 * @template-extends Chain<Action,string>
 */
class DispatchAction extends Chain
{
}
