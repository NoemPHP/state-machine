<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: YAML processor creates AddCallback with DefaultCallbackType when no async object
 * Intent: Maintains backward compatibility by defaulting to sync callback type for legacy YAML
 */
#[Group('async'), Group('integration'), Group('yaml')]
class YamlSyncChannelDefaultTest extends TestCase
{
    public function testYamlProcessorCreatesAddCallbackWithDefaultCallbackTypeByDefault(): void
    {
        /**
         * ======================================================================
         * INTENTIONALLY SKIPPED - STUB TEST FOR FUTURE IMPLEMENTATION
         * ======================================================================
         *
         * This test is marked as skipped because the specification has been
         * approved but implementation is pending. This is intentional - the test
         * exists as a placeholder to ensure all acceptance criteria are tracked.
         *
         * Acceptance Criterion: YAML processor creates AddCallback with DefaultCallbackType when no async object
         *
         * When implementing, refer to the specification for detailed requirements
         * and ensure all acceptance criteria are met before marking as complete.
         *
         * Related spec: specs/features/async.yaml
         * ======================================================================
         */
        $this->markTestSkipped(
            'Test implementation pending - needs YAML loading infrastructure'
        );

        // TODO: Implement test that verifies:
        // 1. Load YAML without async config
        // 2. Verify callbacks are registered with DefaultCallbackType
        // 3. Ensure no AsyncCallbackType is used for non-async callbacks
    }
}
