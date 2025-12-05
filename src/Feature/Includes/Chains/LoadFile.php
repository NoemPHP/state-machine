<?php

declare(strict_types=1);

namespace Noem\State\Feature\Includes\Chains;

use Noem\State\Feature\Includes\LoadFileParams;
use Noem\State\Middleware\Chain;

/**
 * Chain for loading file contents with middleware support.
 *
 * Default provider reads files using file_get_contents.
 * Middleware can customize behavior for:
 * - Search path resolution
 * - Caching
 * - Path transformations
 * - Content preprocessing
 *
 * @template-extends Chain<LoadFileParams, string>
 */
class LoadFile extends Chain
{
    public function __construct()
    {
        parent::__construct(
            provider: function (LoadFileParams $params): string {
                // Attempt to read the file
                $content = @file_get_contents($params->path);

                if ($content === false) {
                    throw new \RuntimeException(
                        sprintf(
                            'Failed to load file "%s": File does not exist or is not readable',
                            $params->path
                        )
                    );
                }

                return $content;
            },
            middlewares: []
        );
    }
}
