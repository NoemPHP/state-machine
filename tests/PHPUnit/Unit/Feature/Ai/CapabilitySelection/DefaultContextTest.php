<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Ai\CapabilitySelection;

use Noem\State\Feature\Ai\AiConfigFeature;
use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Ai\ModelPool;
use Noem\State\Feature\ExtendedState\BoundAccess;
use Noem\State\Feature\ExtendedState\BoundAccessParams;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Ai\AiFeature
 * @covers \Noem\State\Feature\Ai\AiConfigFeature
 */
class DefaultContextTest extends TestCase
{
    public function testAllAiEntrypointsApplyDefaultContextWhenNotProvided(): void
    {
        $chainMail = new ChainMail();

        // Configure ModelPool with default context
        $config = [
            'modelPool' => [
                [
                    'id' => 'test-model',
                    'provider' => 'openai',
                    'model' => 'gpt-4',
                    'complexity' => 3,
                    'context' => 16,
                    'cost' => 'medium',
                ],
            ],
            'preferences' => [
                'defaultComplexity' => 2,
                'defaultContext' => 8,
            ],
        ];

        $configFeature = new AiConfigFeature($config);
        $configFeature($chainMail);

        $aiFeature = new AiFeature();
        $aiFeature($chainMail);

        // Verify ModelPool is available
        $modelPool = $chainMail->get(ModelPool::class);
        $this->assertInstanceOf(ModelPool::class, $modelPool);
        $this->assertEquals(8, $modelPool->getDefaultContext());
    }
}
