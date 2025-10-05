<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: enableFeatures accepts multiple features as variadic parameters
 */
#[Group('region-builder')]
#[Group('feature-registration')]
class EnableMultipleFeaturesTest extends TestCase
{
    public function testEnableFeaturesAcceptsMultipleFeatures(): void
    {
        $builder = new RegionBuilder();
        $invocations = [];
        
        $feature1 = new class($invocations) implements Feature {
            public function __construct(private array &$invocations) {}
            
            public function __invoke(ChainMail $chainMail): void
            {
                $this->invocations[] = 'feature1';
            }
        };
        
        $feature2 = new class($invocations) implements Feature {
            public function __construct(private array &$invocations) {}
            
            public function __invoke(ChainMail $chainMail): void
            {
                $this->invocations[] = 'feature2';
            }
        };
        
        $feature3 = new class($invocations) implements Feature {
            public function __construct(private array &$invocations) {}
            
            public function __invoke(ChainMail $chainMail): void
            {
                $this->invocations[] = 'feature3';
            }
        };
        
        $builder->enableFeatures($feature1, $feature2, $feature3);
        
        $this->assertCount(3, $invocations, 'All three features should be invoked');
        $this->assertSame(['feature1', 'feature2', 'feature3'], $invocations);
    }
    
    public function testEnableFeaturesCanBeCalledMultipleTimes(): void
    {
        $builder = new RegionBuilder();
        $invocations = [];
        
        $feature1 = new class($invocations) implements Feature {
            public function __construct(private array &$invocations) {}
            
            public function __invoke(ChainMail $chainMail): void
            {
                $this->invocations[] = 'first-call';
            }
        };
        
        $feature2 = new class($invocations) implements Feature {
            public function __construct(private array &$invocations) {}
            
            public function __invoke(ChainMail $chainMail): void
            {
                $this->invocations[] = 'second-call';
            }
        };
        
        $builder->enableFeatures($feature1)
                ->enableFeatures($feature2);
        
        $this->assertSame(['first-call', 'second-call'], $invocations);
    }
}
