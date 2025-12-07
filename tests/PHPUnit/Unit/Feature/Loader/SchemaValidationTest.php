<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray validates configuration against schema
 */
#[Group('loader')]
#[Group('array-processing')]
class SchemaValidationTest extends TestCase
{
    public function testValidatesConfigurationAgainstSchema(): void
    {
        $schema = new Schema();
        $transformArray = new TransformArray();
        $processor = new ProcessArray($schema, $transformArray);

        $validConfig = [
            'states' => [
                ['name' => 'idle'],
                ['name' => 'active'],
            ],
            'initial' => 'idle',
        ];

        $builder = new RegionBuilder();

        // Should not throw exception for valid config
        $result = $processor->fromData($validConfig, $builder);

        $this->assertInstanceOf(RegionBuilder::class, $result);
    }
}
