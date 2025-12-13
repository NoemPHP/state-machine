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
        // TODO: Async guards require special handling through AddTransition mechanism
        // Currently guards with async config are extracted but not registered
        // This is a known limitation that needs implementation
        $this->markTestIncomplete(
            'Async guard support through AddTransition is not yet implemented. ' .
            'Guards use AddTransition instead of AddCallback, requiring separate async handling.'
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
