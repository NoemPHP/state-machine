<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save writes string data to file when invoked
 * Intent: Core write functionality for string data source
 */
final class SaveWritesStringDataTest extends TestCase
{
    public function testSaveWritesStringDataToFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $data = 'test string data';

        $save = new Save($tempFile, $data);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        $writtenContent = file_get_contents($tempFile);
        $this->assertSame($data, $writtenContent);

        unlink($tempFile);
    }
}
