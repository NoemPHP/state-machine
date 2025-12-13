<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackType;
use PHPUnit\Framework\TestCase;

// Multiple test doubles
class TypeOne extends CallbackType
{
}

class TypeTwo extends CallbackType
{
}

class TypeThree extends CallbackType
{
}

/**
 * Test: Multiple CallbackType subclasses can coexist independently
 *
 * Intent: Allows features to define multiple callback channels without interference,
 * supporting extensibility for future callback types
 */
class MultipleCallbackTypesTest extends TestCase
{
    public function testMultipleTypesHaveIndependentSingletons(): void
    {
        $type1 = TypeOne::get();
        $type2 = TypeTwo::get();
        $type3 = TypeThree::get();

        $this->assertNotSame($type1, $type2, 'Different types must have different singleton instances');
        $this->assertNotSame($type1, $type3, 'Different types must have different singleton instances');
        $this->assertNotSame($type2, $type3, 'Different types must have different singleton instances');
    }

    public function testMultipleTypesRetainIndividualIdentity(): void
    {
        $type1 = TypeOne::get();
        $type2 = TypeTwo::get();
        $type3 = TypeThree::get();

        $this->assertInstanceOf(TypeOne::class, $type1);
        $this->assertInstanceOf(TypeTwo::class, $type2);
        $this->assertInstanceOf(TypeThree::class, $type3);

        $this->assertNotInstanceOf(TypeTwo::class, $type1);
        $this->assertNotInstanceOf(TypeThree::class, $type1);
    }

    public function testMultipleTypesCanBeDistinguishedWithIs(): void
    {
        $type1 = TypeOne::get();
        $type2 = TypeTwo::get();

        $this->assertTrue($type1->is(TypeOne::class));
        $this->assertFalse($type1->is(TypeTwo::class));

        $this->assertTrue($type2->is(TypeTwo::class));
        $this->assertFalse($type2->is(TypeOne::class));
    }

    public function testAllTypesExtendCallbackType(): void
    {
        $type1 = TypeOne::get();
        $type2 = TypeTwo::get();
        $type3 = TypeThree::get();

        $this->assertInstanceOf(CallbackType::class, $type1);
        $this->assertInstanceOf(CallbackType::class, $type2);
        $this->assertInstanceOf(CallbackType::class, $type3);
    }
}
