<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\IO;

use Noem\State\Feature\Async\IO\Save;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Save yields during generator consumption
 * Intent: Maintains responsiveness while consuming data source
 */
final class SaveYieldsDuringConsumptionTest extends TestCase
{
    public function testSaveYieldsDuringGeneratorConsumption(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'save_test_');

        $dataGenerator = (function () {
            yield 'chunk1';
            yield 'chunk2';
            yield 'chunk3';
        })();

        $save = new Save($tempFile, $dataGenerator);
        $generator = $save();

        // Verify generator yields during consumption
        $this->assertTrue($generator->valid());
        $generator->next();
        $this->assertTrue($generator->valid());
        $generator->next();
        $this->assertTrue($generator->valid());

        unlink($tempFile);
    }
}
