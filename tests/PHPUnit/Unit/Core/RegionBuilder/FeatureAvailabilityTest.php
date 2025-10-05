<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Features registered are available in built region
 */
#[Group('region-builder')]
#[Group('feature-registration')]
class FeatureAvailabilityTest extends TestCase
{
    public function testFeatureModifiesChainMail(): void
    {
        $builder = new RegionBuilder();
        
        $middlewareExecuted = false;

        $feature = new class($middlewareExecuted) implements Feature {
            public function __construct(private bool &$executed) {}

            public function __invoke(ChainMail $chainMail): void
            {
                // Features can register middleware that executes during the build process
                $chainMail->use(function() {
                    $this->executed = true;
                });
            }
        };
        
        $builder->enableFeatures($feature);
        $builder->setStates('test');
        $builder->build();
        
        $this->assertTrue(
            $middlewareExecuted,
            'Middleware registered by feature should be executed during build'
        );
    }
    
    public function testFeatureCanRegisterMiddleware(): void
    {
        $builder = new RegionBuilder();
        $middlewareExecuted = false;
        
        $feature = new class($middlewareExecuted) implements Feature {
            public function __construct(private bool &$executed) {}
            
            public function __invoke(ChainMail $chainMail): void
            {
                $chainMail->use(function() {
                    $this->executed = true;
                });
            }
        };
        
        $builder->enableFeatures($feature)
                ->setStates('test')
                ->build();
        
        $this->assertTrue(
            $middlewareExecuted,
            'Middleware registered by feature should be executed during build'
        );
    }
}
