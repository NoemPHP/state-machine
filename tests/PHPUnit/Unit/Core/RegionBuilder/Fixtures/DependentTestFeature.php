<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\Fixtures;

use Noem\State\Feature\Feature;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Middleware\ChainMail;

/**
 * Test fixture: Feature depending on BaseTestFeature
 */
#[RequiresFeature(BaseTestFeature::class)]
class DependentTestFeature implements Feature
{
    public static int $invocationCount = 0;
    public static array $invocationOrder = [];

    public function __invoke(ChainMail $chainMail): void
    {
        self::$invocationCount++;
        self::$invocationOrder[] = 'dependent';
    }

    public static function reset(): void
    {
        self::$invocationCount = 0;
        self::$invocationOrder = [];
    }
}
