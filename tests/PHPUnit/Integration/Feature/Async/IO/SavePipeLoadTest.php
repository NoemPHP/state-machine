<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Load;
use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save works with Load generator as data source
 * Intent: Enables file copying through generator pipeline
 */
final class SavePipeLoadTest extends TestCase
{
    public function testSavePipesLoadOutputToFile(): void
    {
        $sourceFile = tempnam(sys_get_temp_dir(), 'save_source_');
        $destFile = tempnam(sys_get_temp_dir(), 'save_dest_');
        $originalContent = 'Source file content to copy';

        file_put_contents($sourceFile, $originalContent);

        $loadGenerator = (new Load($sourceFile))();
        $save = new Save($destFile, $loadGenerator);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        $copiedContent = file_get_contents($destFile);
        $this->assertSame($originalContent, $copiedContent);

        unlink($sourceFile);
        unlink($destFile);
    }
}
