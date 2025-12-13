<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Loader;

use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Callbacks\DefaultCallbackType;
use Noem\State\Feature\Async\AsyncCallbackType;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Action with enabled=false processes as sync
 */
#[Group('async'), Group('yaml-callback-extensions')]
class AsyncDisabledTest extends TestCase
{
    public function testActionWithEnabledFalseProcessesAsSync(): void
    {
        $yaml = <<<YAML
states:
  - name: idle
    action:
      - run: !php |
          return function(object \$trigger) {
            return 'sync result';
          };
        async:
          enabled: false
          priority: high
initial: idle
YAML;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new RegionLoader(),
            new ExtendedState(),
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

        // Query for async callbacks - should be empty when enabled=false
        $asyncRecords = iterator_to_array($registry->query(
            region: $region,
            type: AsyncCallbackType::get(),
            event: 'action',
            state: 'idle'
        ));

        // Query for sync callbacks - should have the callback
        $syncRecords = iterator_to_array($registry->query(
            region: $region,
            type: DefaultCallbackType::get(),
            event: 'action',
            state: 'idle'
        ));

        // When async is disabled, callback should be registered as sync
        $this->assertEmpty(
            $asyncRecords,
            'Should have no async callbacks when enabled=false'
        );
        $this->assertNotEmpty(
            $syncRecords,
            'Should have sync callbacks when enabled=false'
        );
    }
}
