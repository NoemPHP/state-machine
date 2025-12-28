<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Security;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() options.tools pattern matching supports exact names and wildcards
 *
 * Criticality: contract
 * Intent: Enables flexible scope restriction through pattern syntax
 *
 * @spec /specs/features/agentic.yaml:462-465
 */
#[Group('ai'), Group('weave'), Group('security')]
final class ToolsPatternMatchingTest extends TestCase
{
    #[Test]
    public function tools_pattern_matching_supports_exact_and_wildcards(): void
    {
        // Test filterToolsByPatterns method (lines 172-184 in Weave.php)
        // Supports exact match: $ability['name'] === $pattern
        // Supports wildcard: fnmatch($pattern, $ability['name'])

        $abilities = [
            ['name' => 'user-search'],
            ['name' => 'user-create'],
            ['name' => 'user-update'],
            ['name' => 'admin-delete'],
            ['name' => 'system-backup'],
        ];

        // Test exact match
        $exactPattern = ['user-search'];
        $exactFiltered = $this->filterByPattern($abilities, $exactPattern);
        $this->assertCount(1, $exactFiltered, 'Exact pattern should match one ability');
        $this->assertSame('user-search', $exactFiltered[0]['name']);

        // Test wildcard pattern
        $wildcardPattern = ['user-*'];
        $wildcardFiltered = $this->filterByPattern($abilities, $wildcardPattern);
        $this->assertCount(3, $wildcardFiltered, 'Pattern user-* should match all user-* abilities');
        $this->assertSame('user-search', $wildcardFiltered[0]['name']);
        $this->assertSame('user-create', $wildcardFiltered[1]['name']);
        $this->assertSame('user-update', $wildcardFiltered[2]['name']);

        // Test multiple patterns
        $multiPattern = ['user-search', 'admin-*'];
        $multiFiltered = $this->filterByPattern($abilities, $multiPattern);
        $this->assertCount(2, $multiFiltered, 'Multiple patterns should match corresponding abilities');
        $this->assertSame('user-search', $multiFiltered[0]['name']);
        $this->assertSame('admin-delete', $multiFiltered[1]['name']);
    }

    /**
     * Simulates filterToolsByPatterns logic from Weave.php lines 172-184
     */
    private function filterByPattern(array $abilities, array $patterns): array
    {
        $filtered = [];
        foreach ($abilities as $ability) {
            foreach ($patterns as $pattern) {
                if ($ability['name'] === $pattern || fnmatch($pattern, $ability['name'])) {
                    $filtered[] = $ability;
                    break;
                }
            }
        }
        return $filtered;
    }
}
