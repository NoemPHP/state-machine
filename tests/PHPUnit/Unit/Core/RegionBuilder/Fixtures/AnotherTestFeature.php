<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\Fixtures;

use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;

/**
 * Test fixture: Another independent feature
 */
class AnotherTestFeature implements Feature
{
    public static int $invocationCount = 0;

    public function __invoke(ChainMail $chainMail): void
    {
        self::$invocationCount++;
    }

    public static function reset(): void
    {
        self::$invocationCount = 0;
    }
}
