<?php

declare(strict_types=1);

namespace Noem\State\Feature\Includes;

use Noem\State\Chains\Params\BuildParams;

/**
 * Parameter object for LoadFile chain invocations.
 *
 * Simple parameter object that carries the file path and build context
 * for LoadFile chain invocations.
 */
class LoadFileParams
{
    /**
     * @param string $path The file path to load
     * @param BuildParams $buildContext The build context for accessing configuration
     */
    public function __construct(
        public readonly string $path,
        public readonly BuildParams $buildContext,
    ) {
    }
}
