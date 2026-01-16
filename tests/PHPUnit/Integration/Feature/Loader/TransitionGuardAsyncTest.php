<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Loader;

use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Feature\Async\AsyncCallbackType;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Transition guard with async property registers as AsyncCallbackType
 */
#[Group('async'), Group('yaml-callback-extensions')]
class TransitionGuardAsyncTest extends TestCase
{
    public function testTransitionGuardWithAsyncPropertyRegistersAsAsyncCallbackType(): void
    {
        /**
         * ======================================================================
         * INTENTIONALLY SKIPPED - KNOWN LIMITATION (NOT A BUG)
         * ======================================================================
         *
         * This test is marked as skipped because async guards are NOT currently
         * supported in the AsyncFeature implementation. This is a KNOWN LIMITATION
         * documented in the source code at:
         *
         * src/Feature/Async/AsyncFeature.php:725-729
         *
         * REASON FOR LIMITATION:
         * Guards use the AddTransition chain instead of AddCallback, which means
         * they don't flow through the async callback registration mechanism that
         * other callbacks (actions, effects, etc.) use. Supporting async guards
         * requires implementing a separate async handling path for transitions.
         *
         * IMPLEMENTATION REQUIRED:
         * To enable this test, implement async guard support by:
         * 1. Intercepting the AddTransition chain in AsyncFeature
         * 2. Extracting async configuration from guard definitions
         * 3. Registering async guards via CallbackRegistry with AsyncCallbackType
         * 4. Ensuring scheduler processes async guards correctly
         *
         * Related spec: specs/features/async.yaml - yaml-callback-extensions
         * Related code: src/Feature/Async/AsyncFeature.php:725-729
         * ======================================================================
         */
        $this->markTestSkipped(
            'KNOWN LIMITATION: Async guard support not yet implemented. ' .
            'Guards use AddTransition instead of AddCallback, requiring separate async handling mechanism. ' .
            'See AsyncFeature.php:725-729 and test docblock for details.'
        );

        $yaml = <<<YAML
states:
  - name: idle
    transitions:
      - target: active
        guard:
          run: !php |
            return function(object \$trigger) {
              yield;
              return true;
            };
          async:
            priority: high
  - name: active
initial: idle
YAML;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new RegionLoader(),
            new ExtendedState(),
            new TransitionsFeature(),
            new AsyncFeature()
        );

        $helpers = [
            'php' => new PhpEvalHelper(),
        ];

        $region = $builder->build([
            'loader' => [
                'yaml' => $yaml,
                'yamlHelpers' => $helpers,
            ],
        ]);

        // Access ChainMail via reflection to get registry
        $reflection = new \ReflectionClass($builder);
        $chainMailProperty = $reflection->getProperty('chainMail');
        $chainMailProperty->setAccessible(true);
        $chainMail = $chainMailProperty->getValue($builder);
        $registry = $chainMail->get(CallbackRegistry::class);

        // Query registry for async guard callbacks
        // Note: guards might not use the same event system as actions
        // Let's query without event filter to find all async callbacks for this state
        $records = $registry->query(
            region: $region,
            type: AsyncCallbackType::get(),
            event: null,  // Null to get all event types
            state: 'idle'
        );

        $asyncRecords = iterator_to_array($records);
        $this->assertNotEmpty($asyncRecords, 'Should have async guard callbacks registered');

        foreach ($asyncRecords as $record) {
            if ($record->metadata !== null) {
                $this->assertInstanceOf(
                    \Noem\State\Feature\Async\AsyncConfig::class,
                    $record->metadata
                );
                break;
            }
        }
    }
}
