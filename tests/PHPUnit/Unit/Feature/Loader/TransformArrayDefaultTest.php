<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: TransformArray chain passes through array by default
 */
#[Group('loader')]
#[Group('loader-chains')]
class TransformArrayDefaultTest extends TestCase
{
    public function testTransformArrayPassesThroughByDefault(): void
    {
        $chain = new TransformArray();
        
        $input = [
            'states' => [
                ['name' => 'idle'],
                ['name' => 'running'],
            ],
            'initial' => 'idle',
        ];
        
        $result = $chain->call($input);
        
        $this->assertSame($input, $result, 'TransformArray should pass through array unchanged by default');
    }
    
    public function testTransformArrayPreservesArrayStructure(): void
    {
        $chain = new TransformArray();
        
        $input = [
            'nested' => [
                'deep' => [
                    'value' => 123,
                ],
            ],
        ];
        
        $result = $chain->call($input);
        
        $this->assertSame($input, $result);
    }
}
