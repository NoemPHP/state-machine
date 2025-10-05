<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\BuildStep;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Build steps are executed in order during the build process
 */
#[Group('region-builder')]
#[Group('build-steps')]
class BuildStepExecutionTest extends TestCase
{
    public function testBuildStepsExecuteInRegistrationOrder(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $executionOrder = [];
        
        $stepOne = new class($executionOrder) implements BuildStep {
            public function __construct(private array &$order) {}
            
            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $this->order[] = 'step1';
                return $next($builder);
            }
        };
        
        $stepTwo = new class($executionOrder) implements BuildStep {
            public function __construct(private array &$order) {}
            
            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $this->order[] = 'step2';
                return $next($builder);
            }
        };
        
        $stepThree = new class($executionOrder) implements BuildStep {
            public function __construct(private array &$order) {}
            
            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $this->order[] = 'step3';
                return $next($builder);
            }
        };
        
        $builder->addBuildStep($stepOne)
                ->addBuildStep($stepTwo)
                ->addBuildStep($stepThree);
        
        $region = $builder->build();
        
        $this->assertEquals(['step1', 'step2', 'step3'], $executionOrder, 
            'Build steps should execute in registration order');
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testAllBuildStepsExecuteDuringBuild(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $step1Executed = false;
        $step2Executed = false;
        $step3Executed = false;
        
        $builder->addBuildStep(new class($step1Executed) implements BuildStep {
            public function __construct(private bool &$executed) {}
            public function callback(RegionBuilder $builder, callable $next, callable $first): Region {
                $this->executed = true;
                return $next($builder);
            }
        });
        
        $builder->addBuildStep(new class($step2Executed) implements BuildStep {
            public function __construct(private bool &$executed) {}
            public function callback(RegionBuilder $builder, callable $next, callable $first): Region {
                $this->executed = true;
                return $next($builder);
            }
        });
        
        $builder->addBuildStep(new class($step3Executed) implements BuildStep {
            public function __construct(private bool &$executed) {}
            public function callback(RegionBuilder $builder, callable $next, callable $first): Region {
                $this->executed = true;
                return $next($builder);
            }
        });
        
        $builder->build();
        
        $this->assertTrue($step1Executed, 'First build step should execute');
        $this->assertTrue($step2Executed, 'Second build step should execute');
        $this->assertTrue($step3Executed, 'Third build step should execute');
    }
}
