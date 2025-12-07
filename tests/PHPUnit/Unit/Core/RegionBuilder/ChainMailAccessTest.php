<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Builder exposes ChainMail instance through public readonly property
 */
#[Group('region-builder')]
#[Group('chainmail-integration')]
class ChainMailAccessTest extends TestCase
{
    public function testBuilderExposesChainMailProperty(): void
    {
        $builder = new RegionBuilder();

        $this->assertInstanceOf(
            ChainMail::class,
            $builder->chainMail,
            'Builder should expose ChainMail instance'
        );
    }

    public function testChainMailPropertyIsReadonly(): void
    {
        $builder = new RegionBuilder();
        $chainMail = $builder->chainMail;

        // Attempt to set should fail (PHP 8.4 private(set) property)
        try {
            $builder->chainMail = new ChainMail();
            $this->fail('Should not be able to set private(set) chainMail property');
        } catch (\Error $e) {
            $this->assertStringContainsString(
                'Cannot modify private(set) property',
                $e->getMessage()
            );
        }

        // ChainMail should still be the same instance
        $this->assertSame($chainMail, $builder->chainMail);
    }

    public function testCustomChainMailIsExposed(): void
    {
        // Create a custom ChainMail with required services
        $customChainMail = new ChainMail();
        $customChainMail->supply(
            fn(): \Noem\State\Chains\ConnectedRegions => new \Noem\State\Chains\ConnectedRegions(),
            fn(\Noem\State\Chains\ConnectedRegions $connections): \Noem\State\Chains\Meta => new \Noem\State\Chains\Meta($connections)
        );

        $builder = new RegionBuilder($customChainMail);

        $this->assertSame(
            $customChainMail,
            $builder->chainMail,
            'Custom ChainMail should be accessible'
        );
    }
}
