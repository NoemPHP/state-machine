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
 * Acceptance Criterion: LoadFile chain default provider reads from filesystem using file_get_contents
 */
#[Group('includes')]
#[Group('file-loading')]
class LoadFileDefaultProviderTest extends TestCase
{
    public function testDefaultProviderUsesFileGetContents(): void
    {
        // Create a temporary file with specific content
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $expectedContent = "Content loaded via file_get_contents\nWith newlines";
        file_put_contents($tempFile, $expectedContent);

        try {
            $chainMail = new ChainMail();
            $feature = new IncludesFeature();
            $feature($chainMail);

            $loadFile = $chainMail->get(LoadFile::class);
            $buildContext = $this->createMock(BuildParams::class);
            $params = new LoadFileParams($tempFile, $buildContext);

            $content = $loadFile->call($params);

            // Verify content matches what file_get_contents would return
            $this->assertSame($expectedContent, $content);
            $this->assertSame(file_get_contents($tempFile), $content);
        } finally {
            unlink($tempFile);
        }
    }
}
