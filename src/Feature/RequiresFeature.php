<?php

namespace Noem\State\Feature;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RequiresFeature
{
    /**
     * @param class-string $featureFQCN
     */
    public function __construct(public string $featureFQCN)
    {

    }
}