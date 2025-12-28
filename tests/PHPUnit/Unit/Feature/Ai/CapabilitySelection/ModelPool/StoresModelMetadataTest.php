<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Ai\CapabilitySelection\ModelPool;

use Noem\State\Feature\Ai\ModelPool;
use PHPUnit\Framework\TestCase;

class StoresModelMetadataTest extends TestCase
{
    public function testStoresModelsWithCapabilityMetadata(): void
    {
        $pool = new ModelPool();

        $pool->addModel(
            id: 'gpt-4',
            provider: 'openai',
            model: 'gpt-4-turbo',
            complexity: 4,
            context: 128,
            cost: 'high'
        );

        $result = $pool->selectModel(complexity: 4, context: 128);

        $this->assertNotNull($result);
        $this->assertEquals('gpt-4', $result['id']);
        $this->assertEquals('openai', $result['provider']);
        $this->assertEquals(4, $result['complexity']);
        $this->assertEquals(128, $result['context']);
    }
}
