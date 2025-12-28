<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Ai\CapabilitySelection\ModelPool;

use Noem\State\Feature\Ai\ModelPool;
use PHPUnit\Framework\TestCase;

class FallsBackToHigherTest extends TestCase
{
    public function testFallsBackToHigherCapabilityWhenNoExactMatch(): void
    {
        $pool = new ModelPool();

        $pool->addModel('higher', 'openai', 'gpt-4', 4, 128, 'high');

        $result = $pool->selectModel(complexity: 2, context: 8);

        $this->assertNotNull($result);
        $this->assertEquals('higher', $result['id']);
    }
}
