<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\Fixtures;

use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;

/**
 * Test fixture: Level A (no dependencies)
 */
class TransitiveTestFeatureA implements Feature
{
    public static bool $invoked = false;

    public function __invoke(ChainMail $chainMail): void
    {
        self::$invoked = true;
        // Share invocation order with TransitiveTestFeatureC
        TransitiveTestFeatureC::$invocationOrder[] = 'A';
    }

    public static function reset(): void
    {
        self::$invoked = false;
    }
}
