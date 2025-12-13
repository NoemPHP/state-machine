<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Loader;

use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Feature\Async\AsyncCallbackType;
use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Priority;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: YAML with all async configuration options works
 */
#[Group('async'), Group('yaml-callback-extensions')]
class FullAsyncConfigTest extends TestCase
{
    public function testYamlWithAllAsyncConfigurationOptionsWorks(): void
    {
        $yaml = <<<YAML
states:
  - name: idle
    action:
      - run: !php |
          return function(object \$trigger) {
            yield;
            return 'complete';
          };
        async:
          enabled: true
          priority: high
          singleton: true
          timeout: 10.0
          debounce: 0.5
          throttle: 1.0
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

        // Query registry to verify AsyncConfig was properly created
        $records = $registry->query(
            region: $region,
            type: AsyncCallbackType::get(),
            event: 'action',
            state: 'idle'
        );

        $asyncRecords = iterator_to_array($records);
        $this->assertNotEmpty($asyncRecords, 'Should have async callbacks registered');

        // Find the callback with metadata
        $config = null;
        foreach ($asyncRecords as $record) {
            if ($record->metadata instanceof AsyncConfig) {
                $config = $record->metadata;
                break;
            }
        }

        $this->assertNotNull($config, 'Should have AsyncConfig metadata');
        $this->assertInstanceOf(AsyncConfig::class, $config);

        // Verify all configuration options were applied
        // Note: enabled=true means callback was registered as async (which we verified above)
        // The enabled flag is not stored in AsyncConfig itself
        $this->assertEquals(Priority::HIGH, $config->priority);
        $this->assertTrue($config->singleton);
        $this->assertEquals(10.0, $config->timeout);
        $this->assertEquals(0.5, $config->debounce);
        $this->assertEquals(1.0, $config->throttle);
    }
}
