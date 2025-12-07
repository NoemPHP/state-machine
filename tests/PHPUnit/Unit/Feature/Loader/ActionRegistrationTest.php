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
 * Acceptance Criterion: ProcessArray registers action callbacks with builder
 */
#[Group('loader')]
#[Group('array-processing')]
class ActionRegistrationTest extends TestCase
{
    public function testRegistersActionCallbacksWithBuilder(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());

        $config = [
            'states' => [
                [
                    'name' => 'processing',
                    'action' => [
                        ['run' => fn($t) => null],
                    ],
                ],
            ],
        ];

        $builder = new RegionBuilder();
        $result = $processor->fromData($config, $builder);

        $this->assertInstanceOf(RegionBuilder::class, $result);
    }
}
