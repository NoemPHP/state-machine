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
 * Acceptance Criterion: ProcessArray sets factory when specified in configuration
 */
#[Group('loader')]
#[Group('array-processing')]
class FactorySettingTest extends TestCase
{
    public function testSetsFactoryWhenSpecified(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());

        $factory = function () {
            return new \stdClass();
        };

        $config = [
            'states' => [['name' => 'idle']],
            'factory' => $factory,
        ];

        $builder = new RegionBuilder();
        $result = $processor->fromData($config, $builder);

        $this->assertInstanceOf(RegionBuilder::class, $result);
    }
}
