<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Exec;
use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save works with Exec generator as data source
 * Intent: Enables command output redirection to file
 */
final class SavePipeExecTest extends TestCase
{
    public function testSavePipesExecOutputToFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $command = 'echo "Hello from Exec"';

        $execGenerator = (new Exec($command))();
        $save = new Save($tempFile, $execGenerator);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('Hello from Exec', $content);

        unlink($tempFile);
    }
}
