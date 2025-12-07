<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Spawn schema accepts region property as array
 */
#[Group('loader')]
#[Group('spawn-schema-extension')]
class SpawnRegionSchemaTest extends TestCase
{
    public function testSpawnSchemaAcceptsRegionProperty(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $yaml = <<<YAML
        states:
          - name: parent
            spawn:
              - guard: !php "fn(object \$t): bool => true"
                region:
                  states:
                    - name: child1
                    - name: child2
                  initial: child1
                  final: child2
        initial: parent
        YAML;

        $helpers = [
            'php' => fn(string $code) => eval("return $code;"),
        ];

        // Should build successfully with region property as array
        $region = $builder->build([
            'loader' => [
                'yaml' => $yaml,
                'yamlHelpers' => $helpers,
            ],
        ]);

        $this->assertTrue($region->isInState('parent'));
    }
}
