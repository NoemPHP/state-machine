<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async\IO;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\IO\Save;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save works with Call::call() invocation
 * Intent: Compatible with async coroutine patterns
 */
final class SaveWithCallTest extends TestCase
{
    public function testSaveWorksWithCallInvocation(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $data = 'test via Call::call()';

        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $region = $builder
            ->setStates('active')
            ->onAction('active', function (object $event) use ($tempFile, $data) {
                $buffer = [];
                yield Call::call(new Save($tempFile, $data), $buffer);
            })
            ->build();

        // Trigger multiple times to complete async operation
        for ($i = 0; $i < 10; $i++) {
            $region->trigger(new \stdClass());
        }

        $content = file_get_contents($tempFile);
        $this->assertSame($data, $content);

        unlink($tempFile);
    }
}
