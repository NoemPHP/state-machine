<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\Fixtures;

use Noem\State\Feature\Feature;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Middleware\ChainMail;

/**
 * Test fixture: Level B (depends on A)
 */
#[RequiresFeature(TransitiveTestFeatureA::class)]
class TransitiveTestFeatureB implements Feature
{
    public static bool $invoked = false;

    public function __invoke(ChainMail $chainMail): void
    {
        self::$invoked = true;
        // Share invocation order with TransitiveTestFeatureC
        TransitiveTestFeatureC::$invocationOrder[] = 'B';
    }

    public static function reset(): void
    {
        self::$invoked = false;
    }
}
