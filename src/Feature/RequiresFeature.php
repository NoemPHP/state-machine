<?php

namespace Noem\State\Feature;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class RequiresFeature
{
    /**
     * @param class-string $featureFQCN
     */
    public function __construct(public string $featureFQCN)
    {
    }
}
