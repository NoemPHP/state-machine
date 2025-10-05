<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: All builder methods return self for method chaining
 */
#[Group('region-builder')]
#[Group('fluent-api')]
class FluentInterfaceTest extends TestCase
{
    public function testAllBuilderMethodsReturnSelf(): void
    {
        $builder = new RegionBuilder();
        
        $feature = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void {}
        };
        
        // Test all methods that should return $this
        $this->assertSame($builder, $builder->setStates('a', 'b'));
        $this->assertSame($builder, $builder->addState('c'));
        $this->assertSame($builder, $builder->markInitial('a'));
        $this->assertSame($builder, $builder->markFinal('b'));
        $this->assertSame($builder, $builder->enableFeatures($feature));
        $this->assertSame($builder, $builder->setMetaData([], ContextMetaType::get()));
        $this->assertSame($builder, $builder->onAction('a', fn() => null));
        $this->assertSame($builder, $builder->onEnter('a', fn() => null));
        $this->assertSame($builder, $builder->onExit('a', fn() => null));
    }
    
    public function testComplexMethodChaining(): void
    {
        $builder = new RegionBuilder();
        
        $feature = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void {}
        };
        
        // Test complex chaining scenario
        $region = $builder
            ->enableFeatures($feature)
            ->setStates('idle', 'processing', 'complete')
            ->markInitial('idle')
            ->markFinal('complete')
            ->onEnter('processing', fn() => null)
            ->onAction('processing', fn() => null)
            ->onExit('processing', fn() => null)
            ->setMetaData(['key' => 'value'], ContextMetaType::get())
            ->build();
        
        $this->assertTrue($region->isInState('idle'));
    }
}
