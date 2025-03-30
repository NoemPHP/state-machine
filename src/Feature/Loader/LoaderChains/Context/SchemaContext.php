<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\LoaderChains\Context;

use Nette\Schema\Elements\Structure;
use Nette\Schema\Schema;

class SchemaContext
{
    private array $customTypes = [];

    public function __construct(
        public Schema $callback,
        public Structure $action,
        public Structure $state,
        public Structure $region,
    ) {
    }

    public function addCustomSchema(string $name, Schema $value): void
    {
        $this->customTypes[$name] = $value;
    }

    public function getCustomSchema(string $name): ?Schema
    {
        return $this->customTypes[$name] ?? null;
    }
}
