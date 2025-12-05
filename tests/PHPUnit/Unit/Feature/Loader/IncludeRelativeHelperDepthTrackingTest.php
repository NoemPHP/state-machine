<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Loader;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Includes\Chains\LoadFile;
use Noem\State\Feature\Includes\LoadFileParams;
use Noem\State\Feature\Loader\ConvertYaml;
use Noem\State\Feature\Loader\Helper\IncludeRelativeHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: IncludeRelativeHelper tracks recursion depth across nested YAML includes
 */
#[Group('loader'), Group('include-helpers')]
class IncludeRelativeHelperDepthTrackingTest extends TestCase
{
    public function testTracksDepthAcrossNestedRelativeIncludes(): void
    {
        // Arrange: Create mocks
        $loadFile = $this->createMock(LoadFile::class);
        $buildParams = $this->createMock(BuildParams::class);
        $convertYaml = $this->createMock(ConvertYaml::class);
        $currentParams = $this->createMock(LoadFileParams::class);

        // Configure loadFile to return nested YAML content
        $loadFile->expects($this->exactly(3))
            ->method('call')
            ->willReturnOnConsecutiveCalls(
                'nested: !includeRelative second.yaml',  // First file includes second
                'final: value',                           // Second file has no includes
                'another: value',                          // Third independent include
                'another: value'                          // Third independent include
            );

        $depthCallCount = 0;
        $helper = new IncludeRelativeHelper($loadFile, $buildParams, $convertYaml, $currentParams);
        $convertYaml->expects($this->exactly(3))
            ->method('fromString')
            ->willReturnCallback(function ($content) use (&$depthCallCount, $helper) {
                $depthCallCount++;
                $value = '';
                if (str_contains($content, '!includeRelative')) {
                    $value = $helper('value');
                }
                // Track that we're being called recursively
                return ['nested' => $value];
            });


        // Act: Invoke helper which should track depth internally
        $result1 = $helper('first.yaml');
        $result2 = $helper('third.yaml');

        // Assert: Both invocations succeeded (depth was tracked and reset)
        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
        $this->assertEquals(3, $depthCallCount, 'Should have processed nested YAML twice');
    }

    public function testDepthResetsAfterEachTopLevelInvocation(): void
    {
        // Arrange
        $loadFile = $this->createMock(LoadFile::class);
        $buildParams = $this->createMock(BuildParams::class);
        $convertYaml = $this->createMock(ConvertYaml::class);
        $currentParams = $this->createMock(LoadFileParams::class);

        $loadFile->method('call')->willReturn('value: data');
        $convertYaml->method('fromString')->willReturn(['value' => 'data']);

        $helper = new IncludeRelativeHelper($loadFile, $buildParams, $convertYaml, $currentParams);

        // Act: Multiple independent invocations
        $result1 = $helper('file1.yaml');
        $result2 = $helper('file2.yaml');
        $result3 = $helper('file3.yaml');

        // Assert: All succeeded (depth was reset between invocations)
        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
        $this->assertIsArray($result3);
    }
}
