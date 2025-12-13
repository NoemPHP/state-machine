<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Loader;

use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Callbacks\DefaultCallbackType;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Action without async property registers as DefaultCallbackType
 */
#[Group('async'), Group('yaml-callback-extensions')]
class ActionSyncDefaultTest extends TestCase
{
    public function testActionWithoutAsyncPropertyRegistersAsDefaultCallbackType(): void
    {
        $yaml = <<<YAML
states:
  - name: idle
    action:
      - run: !php |
          return function(object \$trigger) {
            return 'sync result';
          };
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

        // Query registry for default (sync) callbacks on idle state
        $records = $registry->query(
            region: $region,
            type: DefaultCallbackType::get(),
            event: 'action',
            state: 'idle'
        );

        // Verify we have sync callbacks registered
        $syncRecords = iterator_to_array($records);
        $this->assertNotEmpty(
            $syncRecords,
            'Should have sync callbacks registered for actions without async property'
        );
    }
}
