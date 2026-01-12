<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Fetch;
use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save works with Fetch generator as data source
 * Intent: Enables HTTP response saving to file
 */
final class SavePipeFetchTest extends TestCase
{
    public function testSavePipesFetchResponseToFile(): void
    {
        $this->markTestSkipped('Requires network access - implement when mocking available');

        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $url = 'https://httpbin.org/get';

        $fetchGenerator = (new Fetch($url))();
        $save = new Save($tempFile, $fetchGenerator);
        $generator = $save();

        // Consume the generator
        while ($generator->valid()) {
            $generator->next();
        }

        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertNotEmpty($content);

        unlink($tempFile);
    }
}
