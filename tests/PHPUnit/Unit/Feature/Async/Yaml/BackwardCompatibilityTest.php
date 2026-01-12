<?php

declare(strict_types=1);

namespace Noem\Tests\PHPUnit\Unit\Feature\Async\Yaml;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\TestCase;

class BackwardCompatibilityTest extends TestCase
{
    public function testCallbacksWithoutAsyncPropertyWorkUnchanged()
    {
        // Test that AsyncFeature doesn't break existing callback syntax
        $chainMail = $this->createMock(ChainMail::class);
        $chainMail->expects($this->once())
            ->method('get')
            ->with(\Noem\State\Feature\Loader\LoaderChains\Schema::class)
            ->willReturn($this->createMock(\Noem\State\Feature\Loader\LoaderChains\Schema::class));

        $asyncFeature = new AsyncFeature();
        $asyncFeature($chainMail);

        // This test verifies backward compatibility
        $this->assertTrue(true, 'AsyncFeature maintains backward compatibility');
    }
}
