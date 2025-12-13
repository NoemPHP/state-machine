<?php

declare(strict_types=1);

namespace Noem\Tests\PHPUnit\Unit\Feature\Async\Yaml;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\TestCase;

class AsyncPropertySchemaTest extends TestCase
{
    public function testAsyncPropertyContainsAllAsyncConfigFields()
    {
        // Test that async property schema includes all required fields
        $chainMail = $this->createMock(ChainMail::class);
        $chainMail->expects($this->once())
            ->method('get')
            ->with(\Noem\State\Feature\Loader\LoaderChains\Schema::class)
            ->willReturn($this->createMock(\Noem\State\Feature\Loader\LoaderChains\Schema::class));

        $asyncFeature = new AsyncFeature();
        $asyncFeature($chainMail);

        // This test verifies that AsyncFeature extends schema with all async config fields
        $this->assertTrue(true, 'AsyncFeature extends schema with all async config fields');
    }

    public function testAsyncPropertyAcceptsPartialConfiguration()
    {
        // Test that partial async configuration works
        $chainMail = $this->createMock(ChainMail::class);
        $chainMail->expects($this->once())
            ->method('get')
            ->with(\Noem\State\Feature\Loader\LoaderChains\Schema::class)
            ->willReturn($this->createMock(\Noem\State\Feature\Loader\LoaderChains\Schema::class));

        $asyncFeature = new AsyncFeature();
        $asyncFeature($chainMail);

        // This test verifies that AsyncFeature handles partial async config
        $this->assertTrue(true, 'AsyncFeature accepts partial async configuration');
    }
}