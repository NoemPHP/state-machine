<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Migration guide documents conversion from implicit to explicit
 * Intent: Provides clear upgrade path for developers, reducing migration friction
 */
#[Group('async'), Group('integration'), Group('backward-compatibility')]
class MigrationGuideExistsTest extends TestCase
{
    public function testMigrationGuideDocumentsConversionFromImplicitToExplicit(): void
    {
        // Check that migration documentation exists
        $possiblePaths = [
            __DIR__ . '/../../../../../docs/async-migration.md',
            __DIR__ . '/../../../../../ASYNC_MIGRATION.md',
            __DIR__ . '/../../../../../docs/migrations/async.md',
        ];

        $found = false;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $found = true;
                $content = file_get_contents($path);
                $this->assertStringContainsString('implicit', strtolower($content), 'Should mention implicit async');
                $this->assertStringContainsString('explicit', strtolower($content), 'Should mention explicit async');
                break;
            }
        }

        // If no migration guide exists yet, that's acceptable - mark as incomplete
        // This is a documentation task, not a code implementation task
        if (!$found) {
            $this->fail('Migration guide should be created at one of: ' . implode(', ', $possiblePaths));
        }
    }
}
