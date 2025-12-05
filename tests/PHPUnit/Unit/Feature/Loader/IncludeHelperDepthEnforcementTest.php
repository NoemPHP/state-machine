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
 * Acceptance Criterion: IncludeHelper throws RuntimeException when recursion depth exceeds MAX_INCLUDE_DEPTH
 */
#[Group('loader'), Group('include-helpers')]
class IncludeHelperDepthEnforcementTest extends TestCase
{
    public function testThrowsExceptionWhenDepthExceedsLimit(): void
    {
        // Arrange: Create deeply nested YAML structure (> 10 levels)
        $loadFile = $this->createMock(LoadFile::class);
        $buildParams = $this->createMock(BuildParams::class);
        $convertYaml = $this->createMock(ConvertYaml::class);
        $currentParams = $this->createMock(LoadFileParams::class);

        // Each file includes another, creating deep nesting
        $loadFile->method('call')->willReturn('nested: !include next.yaml');

        $invocationCount = 0;
        $helper = new IncludeHelper($loadFile, $buildParams, $convertYaml, $currentParams);
        $convertYaml->method('fromString')
            ->willReturnCallback(function ($content, $helpers) use (&$invocationCount, &$helper) {
                $invocationCount++;
                // Recursively call the helper to create depth
                return ['nested' => $helper('next.yaml')];
            });


        // Expect exception with depth limit message
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Include depth exceeded MAX_INCLUDE_DEPTH (10)');

        // Act: Try to load deeply nested structure
        $helper('first.yaml');
    }

    public function testExceptionMessageIncludesFilePath(): void
    {
        // Arrange
        $loadFile = $this->createMock(LoadFile::class);
        $buildParams = $this->createMock(BuildParams::class);
        $convertYaml = $this->createMock(ConvertYaml::class);
        $currentParams = $this->createMock(LoadFileParams::class);

        $loadFile->method('call')->willReturn('nested: !include next.yaml');

        $helper = new IncludeHelper($loadFile, $buildParams, $convertYaml, $currentParams);
        $convertYaml->method('fromString')
            ->willReturnCallback(function ($content, $helpers) use (&$helper) {
                return ['nested' => $helper('problematic.yaml')];
            });


        // Expect exception mentioning the problematic file
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/problematic\.yaml/');

        // Act
        $helper('first.yaml');
    }
}
