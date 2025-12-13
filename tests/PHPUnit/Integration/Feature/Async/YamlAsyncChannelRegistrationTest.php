<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: YAML processor creates AddCallback with AsyncCallbackType when async object present
 * Intent: Registers callbacks in async channel when configured, enabling async execution
 */
#[Group('async'), Group('integration'), Group('yaml')]
class YamlAsyncChannelRegistrationTest extends TestCase
{
    public function testYamlProcessorCreatesAddCallbackWithAsyncCallbackType(): void
    {
        // RED TEST: YAML processor callback type selection not yet implemented
        $this->markTestIncomplete(
            'YAML async channel registration test awaiting loader implementation. ' .
            'When implemented, this should verify that YAML actions with async objects ' .
            'create AddCallback BuildSteps using AsyncCallbackType instead of default.'
        );

        /*
        $yaml = <<<YAML
        states:
          - active
        actions:
          active:
            - async:
                enabled: true
                run: asyncCallback
        YAML;

        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        // Would verify that AddCallback uses AsyncCallbackType
        // This would require inspecting build steps or registry
        $this->assertTrue(true, 'YAML creates AddCallback with AsyncCallbackType');
        */
    }
}
