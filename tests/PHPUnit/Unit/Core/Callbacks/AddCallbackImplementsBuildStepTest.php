<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\BuildStep;
use Noem\State\Callbacks\AddCallback;
use Noem\State\Callbacks\CallbackType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// Test double for CallbackType
class TestCallbackTypeForBuildStep extends CallbackType
{
}

/**
 * Test: AddCallback implements BuildStep interface
 *
 * Intent: Integrates callback registration into the fluent builder pipeline,
 * enabling declarative configuration
 */
#[CoversClass(AddCallback::class)]
final class AddCallbackImplementsBuildStepTest extends TestCase
{
    public function testAddCallbackImplementsBuildStep(): void
    {
        $addCallback = new AddCallback(
            event: 'action',
            state: 'idle',
            callback: fn() => null
        );

        $this->assertInstanceOf(BuildStep::class, $addCallback);
    }

    public function testAddCallbackHasCallbackMethod(): void
    {
        $addCallback = new AddCallback(
            event: 'action',
            state: 'idle',
            callback: fn() => null
        );

        $this->assertTrue(
            method_exists($addCallback, 'callback'),
            'AddCallback must have callback() method from BuildStep interface'
        );
    }
}
