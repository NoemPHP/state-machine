<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async\IO;

use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\IO\Fetch;
use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save can be composed in Call::call(new Save(..., new Fetch(...)))
 * Intent: Enables HTTP response piping to file
 */
final class SaveComposesWithFetchTest extends TestCase
{
    public function testSaveComposesWithFetchViaCall(): void
    {
        $this->markTestSkipped('Requires network access - implement when mocking available');

        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $url = 'https://httpbin.org/json';

        $generator = (function () use ($tempFile, $url) {
            $buffer = [];
            $bytesWritten = yield Call::call(
                new Save($tempFile, (new Fetch($url))()),
                $buffer
            );
            return $bytesWritten;
        })();

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
