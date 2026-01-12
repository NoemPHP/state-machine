<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async\IO;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\IO\Exec;
use Noem\State\Feature\Async\IO\Save;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save can be composed in Call::call(new Save(..., new Exec(...)))
 * Intent: Enables nested I/O operation composition
 */
final class SaveComposesWithExecTest extends TestCase
{
    public function testSaveComposesWithExecViaCall(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');
        $command = 'echo "Composed Exec output"';

        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $region = $builder
            ->setStates('active')
            ->onAction('active', function (object $event) use ($tempFile, $command) {
                $buffer = [];
                yield Call::call(
                    new Save($tempFile, (new Exec($command))()),
                    $buffer
                );
            })
            ->build();

        // Trigger multiple times to complete async operation
        for ($i = 0; $i < 20; $i++) {
            $region->trigger(new \stdClass());
        }

        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('Composed Exec output', $content);

        unlink($tempFile);
    }
}
