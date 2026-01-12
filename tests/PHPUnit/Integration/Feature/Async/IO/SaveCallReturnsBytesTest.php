<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async\IO;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\IO\Save;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Call::call() return value is bytes written from Save
 * Intent: Metadata flows through Call::call() API
 */
final class SaveCallReturnsBytesTest extends TestCase
{
    public function testCallReturnsBytesWritten(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $data = 'test data with known length';
        $bytesWritten = null;

        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $region = $builder
            ->setStates('active')
            ->onAction('active', function (object $event) use ($tempFile, $data, &$bytesWritten) {
                $buffer = [];
                $bytesWritten = yield Call::call(new Save($tempFile, $data), $buffer);
            })
            ->build();

        // Trigger multiple times to complete async operation
        for ($i = 0; $i < 10; $i++) {
            $region->trigger(new \stdClass());
        }

        $this->assertSame(strlen($data), $bytesWritten);

        unlink($tempFile);
    }
}
