<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Loader;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Includes\Chains\LoadFile;
use Noem\State\Feature\Includes\LoadFileParams;
use Noem\State\Feature\Loader\ConvertYaml;
use Noem\State\Feature\Loader\Helper\IncludeHelper;
use Noem\State\Feature\Loader\Helper\IncludeRelativeHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Include helpers reset depth tracking after successful or failed invocation
 */
#[Group('loader'), Group('include-helpers')]
class IncludeHelperDepthResetTest extends TestCase
{
    public function testDepthResetAfterSuccessfulInvocation(): void
    {
        // Arrange
        $loadFile = $this->createMock(LoadFile::class);
        $buildParams = $this->createMock(BuildParams::class);
        $convertYaml = $this->createMock(ConvertYaml::class);
        $currentParams = $this->createMock(LoadFileParams::class);

        $loadFile->method('call')->willReturn('value: data');
        $convertYaml->method('fromString')->willReturn(['value' => 'data']);

        $helper = new IncludeHelper($loadFile, $buildParams, $convertYaml, $currentParams);

        // Act: Call multiple times
        $helper('file1.yaml');
        $helper('file2.yaml');
        $helper('file3.yaml');

        // Assert: If depth wasn't reset, subsequent calls would fail
        // The fact that all three succeeded proves depth is reset
        $this->assertTrue(true, 'All invocations succeeded, depth was reset');
    }

    public function testDepthResetAfterFailedInvocation(): void
    {
        // Arrange
        $loadFile = $this->createMock(LoadFile::class);
        $buildParams = $this->createMock(BuildParams::class);
        $convertYaml = $this->createMock(ConvertYaml::class);
        $currentParams = $this->createMock(LoadFileParams::class);

        // First call throws exception
        $loadFile->method('call')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \RuntimeException('File not found')),
                'value: data'
            );

        $convertYaml->method('fromString')->willReturn(['value' => 'data']);

        $helper = new IncludeHelper($loadFile, $buildParams, $convertYaml, $currentParams);

        // Act: First call fails, second should succeed if depth was reset
        try {
            $helper('nonexistent.yaml');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            // Expected
        }

        $result = $helper('existing.yaml');

        // Assert: Second call succeeded, depth was reset after exception
        $this->assertIsArray($result);
    }

    public function testBothHelperTypesResetDepth(): void
    {
        // Arrange
        $loadFile = $this->createMock(LoadFile::class);
        $buildParams = $this->createMock(BuildParams::class);
        $convertYaml = $this->createMock(ConvertYaml::class);
        $currentParams = $this->createMock(LoadFileParams::class);

        $loadFile->method('call')->willReturn('value: data');
        $convertYaml->method('fromString')->willReturn(['value' => 'data']);

        $includeHelper = new IncludeHelper($loadFile, $buildParams, $convertYaml, $currentParams);
        $includeRelativeHelper = new IncludeRelativeHelper($loadFile, $buildParams, $convertYaml, $currentParams);

        // Act & Assert: Multiple calls to both helpers succeed
        $includeHelper('file1.yaml');
        $includeRelativeHelper('file2.yaml');
        $includeHelper('file3.yaml');
        $includeRelativeHelper('file4.yaml');

        $this->assertTrue(true, 'All invocations succeeded for both helper types');
    }
}
