<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\LoaderChains;

use Noem\State\Middleware\Chain;
use Noem\State\RegionBuilder;

/**
 * @template-extends Chain<array,array>
 */
class TransformArray extends Chain
{
    public function __construct()
    {
        parent::__construct(fn(array $a) => $a);
    }
}
