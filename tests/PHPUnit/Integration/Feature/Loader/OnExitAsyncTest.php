<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Loader;

use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Feature\Async\AsyncCallbackType;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: onExit event with async property registers as AsyncCallbackType
 */
#[Group('async'), Group('yaml-callback-extensions')]
class OnExitAsyncTest extends TestCase
{
    public function testOnExitEventWithAsyncPropertyRegistersAsAsyncCallbackType(): void
    {
        $yaml = <<<YAML
states:
  - name: idle
    onExit:
      - run: !php |
          return function(object \$trigger) {
            yield;
            return 'exited';
          };
        async:
          priority: low
          timeout: 2.0
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

        $records = $registry->query(
            region: $region,
            type: AsyncCallbackType::get(),
            event: 'exit',
            state: 'idle'
        );

        $asyncRecords = iterator_to_array($records);
        $this->assertNotEmpty($asyncRecords, 'Should have async onExit callbacks registered');
    }
}
