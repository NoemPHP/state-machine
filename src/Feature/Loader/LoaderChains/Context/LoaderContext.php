<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\LoaderChains\Context;

class LoaderContext
{
    public bool $recursion = false;

    public mixed $data = false;
}
