<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Includes;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Feature\Includes\Chains\LoadFile;
use Noem\State\Feature\Includes\IncludesFeature;
use Noem\State\Feature\Includes\LoadFileParams;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: LoadFile chain loads file contents from readable path
 */
#[Group('includes')]
#[Group('file-loading')]
class LoadFileTest extends TestCase
{
    public function testLoadsFileContentsFromReadablePath(): void
    {
        // Create a temporary file with test content
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $testContent = "test file content\nmultiline content";
        file_put_contents($tempFile, $testContent);

        try {
            $chainMail = new ChainMail();
            $feature = new IncludesFeature();
            $feature($chainMail);

            $loadFile = $chainMail->get(LoadFile::class);
            $buildContext = $this->createMock(BuildParams::class);
            $params = new LoadFileParams($tempFile, $buildContext);

            $content = $loadFile->call($params);

            $this->assertSame($testContent, $content);
        } finally {
            unlink($tempFile);
        }
    }
}
