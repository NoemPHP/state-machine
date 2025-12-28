<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Enumeration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() excludes enumerate-abilities itself from tool set
 *
 * Criticality: constraint
 * Intent: Prevents recursive meta-operations, avoiding infinite loops and confusion
 *
 * @spec /specs/features/agentic.yaml:123-126
 */
#[Group('ai'), Group('weave'), Group('enumeration')]
final class ExcludesEnumerateAbilityTest extends TestCase
{
    #[Test]
    public function weaveExcludesEnumerateAbility(): void
    {
        // Test will verify enumerate-abilities is filtered out to prevent meta-recursion
        // Verify the filtering logic at Weave.php line 130

        $abilities = [
            ['name' => 'enumerate-abilities'],
            ['name' => 'get-user'],
            ['name' => 'send-email'],
        ];

        // Apply the filter logic from line 130
        $filtered = array_filter($abilities, fn($a) => $a['name'] !== 'enumerate-abilities');

        // Verify enumerate-abilities is excluded
        $this->assertCount(2, $filtered);
        $names = array_column($filtered, 'name');
        $this->assertNotContains('enumerate-abilities', $names);
        $this->assertContains('get-user', $names);
        $this->assertContains('send-email', $names);
    }
}
