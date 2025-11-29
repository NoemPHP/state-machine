<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Spawn schema accepts shared property as array
 */
#[Group('loader')]
#[Group('spawn-schema-extension')]
class SpawnSharedSchemaTest extends TestCase
{
    public function testSpawnSchemaAcceptsSharedProperty(): void
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
                    - name: child
                  initial: child
                shared:
                  meta: true
        initial: parent
        YAML;
        
        $helpers = [
            'php' => fn(string $code) => eval("return $code;"),
        ];
        
        // Should build successfully with shared property
        $region = $builder->build([
            'loader' => [
                'yaml' => $yaml,
                'yamlHelpers' => $helpers,
            ],
        ]);
        
        $this->assertTrue($region->isInState('parent'));
    }
}
