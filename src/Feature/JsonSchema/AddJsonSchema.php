<?php

namespace Noem\State\Feature\JsonSchema;

use Noem\State\BuildStep;
use Noem\State\Chains\Meta;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Region;
use Noem\State\RegionBuilder;

class AddJsonSchema implements BuildStep
{
    public function __construct(private readonly array $schema)
    {

    }

    public function callback(RegionBuilder $builder, callable $next, callable $first): Region
    {
        $meta = $builder->chainMail->get(Meta::class);
        $region = $next($builder);
        $metadata = $meta->call(new \Noem\State\Chains\Params\Meta($region, ContextMetaType::get()));
        foreach ($this->schema as $type) {
            $metadata[$type['name']] = $type['default'] ?? null;
        }

        return $region;
    }
}