<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Ai\CapabilitySelection\ModelPool;

use Noem\State\Feature\Ai\ModelPool;
use PHPUnit\Framework\TestCase;

class SelectsMatchingModelTest extends TestCase
{
    public function testSelectsModelMatchingComplexityAndContext(): void
    {
        $pool = new ModelPool();

        $pool->addModel('gpt-3', 'openai', 'gpt-3.5', 2, 8, 'low');
        $pool->addModel('gpt-4', 'openai', 'gpt-4', 4, 128, 'high');

        $result = $pool->selectModel(complexity: 2, context: 8);

        $this->assertNotNull($result);
        $this->assertEquals('gpt-3', $result['id']);
    }
}
