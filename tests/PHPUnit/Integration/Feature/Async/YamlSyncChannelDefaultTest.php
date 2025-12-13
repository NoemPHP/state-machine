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
        $this->markTestIncomplete('Test implementation pending - needs YAML loading infrastructure');

        // TODO: Implement test that verifies:
        // 1. Load YAML without async config
        // 2. Verify callbacks are registered with DefaultCallbackType
        // 3. Ensure no AsyncCallbackType is used for non-async callbacks
    }
}
