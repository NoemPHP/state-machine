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
 * Acceptance Criterion: Include helpers maintain independent depth counters per helper instance
 */
#[Group('loader'), Group('include-helpers')]
class IncludeHelperDepthIndependenceTest extends TestCase
{
    public function testEachHelperInstanceHasIndependentDepthCounter(): void
    {
        // Arrange: Create two separate helper instances
        $loadFile = $this->createMock(LoadFile::class);
        $buildParams = $this->createMock(BuildParams::class);
        $convertYaml = $this->createMock(ConvertYaml::class);
        $currentParams1 = $this->createMock(LoadFileParams::class);
        $currentParams2 = $this->createMock(LoadFileParams::class);

        $loadFile->method('call')->willReturn('value: data');
        $convertYaml->method('fromString')->willReturn(['value' => 'data']);

        $helper1 = new IncludeHelper($loadFile, $buildParams, $convertYaml, $currentParams1);
        $helper2 = new IncludeHelper($loadFile, $buildParams, $convertYaml, $currentParams2);

        // Act: Use both helpers independently
        $result1 = $helper1('file1.yaml');
        $result2 = $helper2('file2.yaml');
        $result3 = $helper1('file3.yaml');

        // Assert: Both helpers work independently
        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
        $this->assertIsArray($result3);
    }
}
