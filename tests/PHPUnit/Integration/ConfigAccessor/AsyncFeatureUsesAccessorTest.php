<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\ConfigAccessor;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature uses AsyncConfig to access resolver definitions
 */
#[Group('config-accessor'), Group('integration')]
class AsyncFeatureUsesAccessorTest extends TestCase
{
    public function testAsyncFeatureUsesAccessor(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new AsyncFeature(),
            new ExtendedState()
        );
        
        $resolverCalled = false;
        $resolvedValue = null;

        $region = $builder
            ->setStates('idle')
            ->markInitial('idle')
            ->onAction('idle', function (object $trigger) use (&$resolverCalled, &$resolvedValue) {
                $resolvedValue = $this->get('testResolver');
                $resolverCalled = true;
            })
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'resolvers' => [
                                [
                                    'name' => 'testResolver',
                                    'run' => function () {
                                        yield;
                                        return 'resolved_value';
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        
        // Trigger several times to progress async tasks
        for ($i = 0; $i < 10; $i++) {
            $region->trigger(new \stdClass());
        }
        
        $this->assertTrue($resolverCalled, 'Resolver should have been called via AsyncConfig accessor');
        $this->assertSame('resolved_value', $resolvedValue);
    }
}
