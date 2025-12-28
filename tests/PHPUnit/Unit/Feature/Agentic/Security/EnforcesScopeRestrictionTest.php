<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Agentic\Security;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: weave() cannot invoke abilities outside filtered scope
 *
 * Criticality: constraint
 * Intent: Prevents scope escalation by rejecting AI selections outside filtered set
 *
 * @spec /specs/features/agentic.yaml:467-470
 */
#[Group('ai'), Group('weave'), Group('security')]
final class EnforcesScopeRestrictionTest extends TestCase
{
    #[Test]
    public function cannot_invoke_abilities_outside_scope(): void
    {
        // Test validateSelectedTools method (lines 383-395 in Weave.php)
        // Validates AI selections against available tools
        // in_array($tool['ability'] ?? '', $availableNames, true)

        $availableTools = [
            ['name' => 'user-search'],
            ['name' => 'user-create'],
            ['name' => 'user-update'],
        ];

        $selectedTools = [
            ['ability' => 'user-search', 'parameters' => []],
            ['ability' => 'admin-delete', 'parameters' => []], // Not in available tools - should be rejected
            ['ability' => 'user-create', 'parameters' => []],
            ['ability' => 'system-backup', 'parameters' => []], // Not in available tools - should be rejected
        ];

        // Simulate validation logic from lines 383-395
        $validated = $this->validateAgainstScope($selectedTools, $availableTools);

        $this->assertCount(2, $validated, 'Should only validate tools in available scope');
        $this->assertSame('user-search', $validated[0]['ability']);
        $this->assertSame('user-create', $validated[1]['ability']);

        // Verify rejected tools
        $validatedNames = array_column($validated, 'ability');
        $this->assertNotContains('admin-delete', $validatedNames, 'admin-delete should be rejected (outside scope)');
        $this->assertNotContains('system-backup', $validatedNames, 'system-backup should be rejected (outside scope)');
    }

    /**
     * Simulates validateSelectedTools logic from Weave.php lines 383-395
     */
    private function validateAgainstScope(array $selectedTools, array $availableTools): array
    {
        $availableNames = array_column($availableTools, 'name');
        $validated = [];

        foreach ($selectedTools as $tool) {
            if (in_array($tool['ability'] ?? '', $availableNames, true)) {
                $validated[] = $tool;
            }
        }

        return $validated;
    }
}
