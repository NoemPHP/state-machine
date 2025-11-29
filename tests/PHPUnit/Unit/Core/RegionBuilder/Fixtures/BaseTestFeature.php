<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\Fixtures;

use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;

/**
 * Test fixture: Feature with no dependencies
 */
class BaseTestFeature implements Feature
{
    public static int $invocationCount = 0;

    public function __invoke(ChainMail $chainMail): void
    {
        self::$invocationCount++;
        // Share invocation order with DependentTestFeature
        DependentTestFeature::$invocationOrder[] = 'base';
    }

    public static function reset(): void
    {
        self::$invocationCount = 0;
    }
}
