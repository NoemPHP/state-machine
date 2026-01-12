<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save writes collected generator values to file
 * Intent: Persists generator output to filesystem
 */
final class SaveWritesGeneratorOutputTest extends TestCase
{
    public function testSaveWritesGeneratorOutput(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');

        $dataGenerator = (function () {
            yield 'Hello ';
            yield 'World';
            yield '!';
        })();

        $save = new Save($tempFile, $dataGenerator);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertSame('Hello World!', $content);

        unlink($tempFile);
    }
}
