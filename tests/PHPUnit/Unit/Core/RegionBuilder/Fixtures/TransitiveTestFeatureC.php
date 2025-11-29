<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\Fixtures;

use Noem\State\Feature\Feature;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Middleware\ChainMail;

/**
 * Test fixture: Level C (depends on B, transitively on A)
 */
#[RequiresFeature(TransitiveTestFeatureB::class)]
class TransitiveTestFeatureC implements Feature
{
    public static array $invocationOrder = [];

    public function __invoke(ChainMail $chainMail): void
    {
        self::$invocationOrder[] = 'C';
    }

    public static function reset(): void
    {
        self::$invocationOrder = [];
    }
}
