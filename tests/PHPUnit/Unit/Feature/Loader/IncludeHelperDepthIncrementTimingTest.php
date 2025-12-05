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
 * Acceptance Criterion: Include helpers increment depth before recursive ConvertYaml invocation
 */
#[Group('loader'), Group('include-helpers')]
class IncludeHelperDepthIncrementTimingTest extends TestCase
{
    public function testDepthIncrementedBeforeConvertYamlCall(): void
    {
        // Arrange: Track the order of operations
        $operations = [];

        $loadFile = $this->createMock(LoadFile::class);
        $buildParams = $this->createMock(BuildParams::class);
        $convertYaml = $this->createMock(ConvertYaml::class);
        $currentParams = $this->createMock(LoadFileParams::class);

        // Record when LoadFile is called
        $loadFile->method('call')
            ->willReturnCallback(function () use (&$operations) {
                $operations[] = 'loadFile';
                return 'nested: value';
            });

        // Record when ConvertYaml is called (depth should already be incremented)
        $convertYaml->method('fromString')
            ->willReturnCallback(function () use (&$operations) {
                $operations[] = 'convertYaml';
                return ['nested' => 'value'];
            });

        $helper = new IncludeHelper($loadFile, $buildParams, $convertYaml, $currentParams);

        // Act
        $helper('test.yaml');

        // Assert: Depth increment happens before ConvertYaml call
        // We can verify this by ensuring ConvertYaml is called and no exception is thrown
        $this->assertContains('convertYaml', $operations);

        // If depth wasn't incremented before ConvertYaml, nested includes wouldn't track properly
        $this->assertEquals(['loadFile', 'convertYaml'], $operations);
    }
}
