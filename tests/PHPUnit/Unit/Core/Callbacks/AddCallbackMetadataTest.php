<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test: AddCallback accepts optional metadata for feature-specific configuration
 *
 * Intent: Allows features to attach custom config (e.g., AsyncConfig) without
 * modifying AddCallback signature
 */
#[CoversClass(AddCallback::class)]
final class AddCallbackMetadataTest extends TestCase
{
    public function testAddCallbackAcceptsMetadata(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);
        $callback = fn() => 'test';
        $metadata = ['timeout' => 5000, 'priority' => 10];

        $builder
            ->addState('idle')
            ->markInitial('idle')
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $callback,
                    metadata: $metadata
                )
            )
            ->build([]);

        $records = $registry->query();
        $this->assertCount(1, $records);
        $this->assertSame($metadata, $records[0]->metadata);
    }

    public function testAddCallbackMetadataDefaultsToNull(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);
        $callback = fn() => 'test';

        $builder
            ->addState('idle')
            ->markInitial('idle')
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $callback
                    // metadata is null (default)
                )
            )
            ->build([]);

        $records = $registry->query();
        $this->assertCount(1, $records);
        $this->assertNull($records[0]->metadata);
    }
}
