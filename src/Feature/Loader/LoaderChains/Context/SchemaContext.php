<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\LoaderChains\Context;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Schema;

class SchemaContext
{
    public function __construct(
        public Schema $callback,
        public Structure $action,
        public Structure $state,
        public Structure $region,
    ) {
    }
}
