<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader extends state schema with spawn property
 */
#[Group('loader')]
#[Group('spawn-schema-extension')]
class SpawnSchemaExtensionTest extends TestCase
{
    public function testExtendsStateSchemaWithSpawnProperty(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        // YAML with spawn property - if schema is extended, this will validate correctly
        $yaml = <<<YAML
        states:
          - name: parent
            spawn:
              - guard: !php "fn(object \$t): bool => true"
                region:
                  states:
                    - name: child
                  initial: child
        initial: parent
        YAML;
        
        $helpers = [
            'php' => fn(string $code) => eval("return $code;"),
        ];
        
        // If spawn schema extension works, this should not throw validation error
        $region = $builder->build([
            'loader' => [
                'yaml' => $yaml,
                'yamlHelpers' => $helpers,
            ],
        ]);
        
        $this->assertTrue($region->isInState('parent'));
    }
}
