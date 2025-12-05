<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Loader;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Includes\Chains\LoadFile;
use Noem\State\Feature\Includes\LoadFileParams;
use Noem\State\Feature\Loader\ConvertYaml;
use Noem\State\Feature\Loader\Helper\IncludeHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Include helpers use MAX_INCLUDE_DEPTH constant of 10 as default limit
 */
#[Group('loader'), Group('include-helpers')]
class IncludeHelperDepthLimitDefaultTest extends TestCase
{
    public function testMaxDepthDefaultIs10(): void
    {
        // Arrange: Create a structure that nests exactly 10 levels deep
        $loadFile = $this->createMock(LoadFile::class);
        $buildParams = $this->createMock(BuildParams::class);
        $convertYaml = $this->createMock(ConvertYaml::class);
        $currentParams = $this->createMock(LoadFileParams::class);

        $depth = 0;
        $loadFile->method('call')->willReturn('nested: !include next.yaml');

        $helper = new IncludeHelper($loadFile, $buildParams, $convertYaml, $currentParams);

        $convertYaml->method('fromString')
            ->willReturnCallback(function ($content, $helpers) use (&$depth, &$helper) {
                $depth++;
                // Stop recursion at depth 10 (should succeed)
                if ($depth < 10) {
                    return ['nested' => $helper('next.yaml')];
                }
                return ['value' => 'final'];
            });


        // Act: Load 10 levels deep (should succeed)
        $result = $helper('first.yaml');

        // Assert: Succeeded at depth 10
        $this->assertIsArray($result);
        $this->assertEquals(10, $depth);
    }

    public function testDepth11ThrowsException(): void
    {
        // Arrange: Create a structure that tries to nest 11 levels deep
        $loadFile = $this->createMock(LoadFile::class);
        $buildParams = $this->createMock(BuildParams::class);
        $convertYaml = $this->createMock(ConvertYaml::class);
        $currentParams = $this->createMock(LoadFileParams::class);

        $loadFile->method('call')->willReturn('nested: !include next.yaml');

        $helper = new IncludeHelper($loadFile, $buildParams, $convertYaml, $currentParams);

        $convertYaml->method('fromString')
            ->willReturnCallback(function ($content, $helpers) use (&$helper) {
                // Always try to recurse (will hit depth limit at 11)
                return ['nested' => $helper('next.yaml')];
            });


        // Expect exception at depth 11
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MAX_INCLUDE_DEPTH (10)');

        // Act: Try to load beyond max depth
        $helper('first.yaml');
    }
}
