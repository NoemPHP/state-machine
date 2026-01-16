# Spec Standard Enhancement: Flexible Test Execution Configuration

## Status

**Proposed** - 2026-01-13

## Problem Statement

The current spec standard uses a simple string field (`test:`) containing a full shell command to execute tests:

```yaml
test: vendor/bin/phpunit tests/PHPUnit/Unit/Core/Region/CurrentStateTrackingTest.php
```

This approach has several limitations:

1. **Limited Control**: No fine-grained control over test bootstrapping, selection, execution, or reporting
2. **Poor Composability**: Cannot easily compose test execution with different configurations or environments
3. **Rigid Format**: Hard to extend with additional execution options without breaking existing specs
4. **Tool-Specific Knowledge**: Requires encoding tool-specific flags directly in command strings
5. **No Type Safety**: Cannot validate configuration structure or provide IDE support
6. **Environment Issues**: No standardized way to specify environment variables or execution context
7. **Single Test Limitation**: Cannot express multiple test execution strategies for the same spec
8. **No Stable Identifiers**: Specs lack machine-friendly identifiers for linking tests via attributes or tracking across refactors

## Goals

1. **Flexibility**: Support both generic shell commands and tool-specific structured configurations
2. **Clean Slate**: No backward compatibility - build new runner for new format only (simpler, faster)
3. **Type Safety**: Enable validation and IDE support through structured configuration
4. **Extensibility**: Easy to add new test runner types or configuration options
5. **Composability**: Allow reusable configuration fragments across specs
6. **Contract Safety**: Provide clear, validated contracts for test execution requirements
7. **Future-Proof**: Support multiple tests per spec at the schema level (even if policy enforces 1:1)

## Proposed Solution

### 1. Test Execution Type System

The `test` field will accept **a list of test configuration objects**. While the project policy maintains the "1 spec = 1 test" rule, the underlying schema supports multiple tests to enable:

- Schema composition via `oneOf` unions
- Future extensibility without schema breaking changes
- Validation tooling that works with diverse test configurations

#### Format Overview

```yaml
# Single test (list with one item)
tests:
  - type: phpunit
    target: tests/PHPUnit/Unit/TestFile.php

# Multiple tests (future use, not current policy)
tests:
  - type: phpunit
    target: tests/PHPUnit/Unit/TestFile.php
  - type: generic
    command: ./scripts/additional-validation.sh
```

### 2. Spec Identifier Requirement

**Every spec must have a unique `id` field** - a machine-friendly slug that enables:
- Stable test-to-spec linking via PHPUnit attributes
- Spec tracking across refactors
- Better tooling for validation and generation
- Programmatic spec querying

**Format**: `{feature-name}:{descriptive-slug}` (kebab-case with colon separator)

**Naming Convention**: Feature and spec are separated by `:` for clarity

This prevents collisions between features while maintaining readability and parseability.

**Example**:
```yaml
features:
  - name: state-management
    specs:
      - id: state-management:region-tracks-current-state
        acceptanceCriteria: A region tracks its current state
        criticality: contract
        scope: unit
        intent: Provides runtime visibility
        tests:
          - type: phpunit
            target: tests/PHPUnit/Unit/Core/Region/CurrentStateTrackingTest.php
```

**Collision Mitigation**:
- Spec IDs **must** use format `{feature}:{spec}`
- Colon separator makes feature/spec boundary explicit
- Easy to parse and validate with regex
- Global uniqueness guaranteed through feature namespace
- Clear ownership and context for every spec

### 3. Test Scope Classification (Mandatory)

**Every spec must have a `scope` field** - defines where the test sits in the test pyramid:

- `unit` - Isolated component/function tests
- `integration` - Feature interaction tests
- `e2e` - End-to-end user journey tests

**Purpose**:
- Filter tests by pyramid level (e.g., run only E2E smoke tests)
- Understand test distribution across the pyramid
- Enable fast feedback loops (skip unit tests in quick validation)
- CI/CD optimization (different scopes for different pipeline stages)

**Example**:
```yaml
features:
  - name: state-management
    specs:
      # Unit test - isolated behavior
      - id: state-management:region-tracks-current-state
        acceptanceCriteria: A region tracks its current state
        criticality: contract
        scope: unit  # ← MANDATORY
        intent: Provides runtime visibility
        tests:
          - type: phpunit
            target: tests/PHPUnit/Unit/Core/Region/CurrentStateTrackingTest.php

      # Integration test - feature interaction
      - id: state-management:async-state-coordination
        acceptanceCriteria: Async feature coordinates with state management
        criticality: contract
        scope: integration  # ← MANDATORY
        intent: Feature interaction validation
        tests:
          - type: phpunit
            target: tests/PHPUnit/Integration/Feature/AsyncStateTest.php

      # E2E test - full workflow
      - id: state-management:complete-lifecycle
        acceptanceCriteria: A complete state machine executes successfully
        criticality: contract
        scope: e2e  # ← MANDATORY
        intent: End-to-end validation
        tests:
          - type: phpunit
            target: tests/PHPUnit/E2E/Machines/FullLifecycleTest.php
```

**Runner Filtering** (future enhancement):
```bash
# Smoke test: Run only E2E tests
./run.sh --scope=e2e

# Quick validation: Skip unit tests
./run.sh --scope=integration,e2e

# Pre-commit: Fast tests only
./run.sh --scope=unit

# Combined filtering: Critical E2E tests only
./run.sh --scope=e2e --criticality=contract
```

**Guidelines**:
- **Unit**: Tests ONE component in isolation (mocked dependencies)
- **Integration**: Tests interaction between 2+ features/components
- **E2E**: Tests complete user workflows through multiple layers

**Linking Tests via PHPUnit Attributes**:

Use PHPUnit's built-in `#[Ticket]` attribute to link tests to spec IDs:

```php
namespace Noem\State\Test\Unit\Core\Region;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Ticket;

#[Ticket('state-management:region-tracks-current-state')]
class CurrentStateTrackingTest extends TestCase
{
    public function testRegionTracksCurrentState(): void
    {
        // Test implementation
    }
}
```

**Alternative: Use `#[Group]` for spec grouping**:

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('spec:state-management:region-tracks-current-state')]
class CurrentStateTrackingTest extends TestCase
{
    // ...
}
```

**Benefits**:
- Uses standard PHPUnit attributes (no custom code needed)
- Tests can be discovered and filtered by spec ID
- Validation tools can verify test coverage per spec
- Refactoring acceptance criteria doesn't break links
- Machine-readable references for documentation generation
- Works with existing PHPUnit tooling

### 3. Test Type Definitions

#### 3.1 Generic Test Type

```yaml
tests:
  - type: generic
    command: vendor/bin/phpunit tests/PHPUnit/Unit/Core/Region/CurrentStateTrackingTest.php
    environment:
      XDEBUG_MODE: coverage
      APP_ENV: test
    workingDirectory: /path/to/project  # optional
    timeout: 300  # optional, seconds
```

**Use Cases**:
- Custom test runners
- Shell scripts
- Docker-based test execution
- Complex commands requiring environment setup
- Migration from legacy string format

**Required Fields**: `type`, `command`

#### 2.2 PHPUnit Test Type

```yaml
tests:
  - type: phpunit

    # Selection Options (at least one required)
    target: tests/PHPUnit/Unit/Core/Region/CurrentStateTrackingTest.php  # file/directory
    filter: testMethodName  # --filter
    group: [unit, core]  # --group
    excludeGroup: [integration]  # --exclude-group
    testsuite: unit  # --testsuite
    excludeTestsuite: [e2e]  # --exclude-testsuite

    # Configuration Options
    configuration: phpunit.xml  # -c
    noConfiguration: false  # --no-configuration
    bootstrap: tests/bootstrap.php  # --bootstrap

    # Execution Options
    stopOnDefect: false  # --stop-on-defect
    stopOnFailure: false  # --stop-on-failure
    stopOnError: false  # --stop-on-error
    failOnWarning: true  # --fail-on-warning

    # Reporting Options
    colors: auto  # --colors (auto|always|never)
    testdox: false  # --testdox
    verbose: 0  # 0, 1, 2, 3 (maps to -v, -vv, -vvv)

    # Coverage Options
    coverageHtml: build/coverage  # --coverage-html
    coverageClover: build/logs/clover.xml  # --coverage-clover

    # Environment
    environment:
      XDEBUG_MODE: coverage

    # Advanced
    processIsolation: false  # --process-isolation
    noExtensions: false  # --no-extensions
    timeout: 300  # execution timeout in seconds
```

**Required Fields**: `type`, and at least one selection option (`target`, `filter`, `group`, etc.)

### 4. JSON Schema Definition

The spec YAML schema uses `oneOf` for type-safe validation:

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "properties": {
    "name": { "type": "string" },
    "group": { "type": "string" },
    "description": { "type": "string" },
    "features": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "name": { "type": "string" },
          "description": { "type": "string" },
          "specs": {
            "type": "array",
            "items": {
              "type": "object",
              "properties": {
                "id": {
                  "type": "string",
                  "pattern": "^[a-z0-9]+(-[a-z0-9]+)*:[a-z0-9]+(-[a-z0-9]+)*$",
                  "minLength": 5,
                  "maxLength": 70,
                  "description": "Machine-friendly spec identifier (kebab-case with colon separator) must use {feature}:{spec} format"
                },
                "acceptanceCriteria": { "type": "string" },
                "criticality": {
                  "type": "string",
                  "enum": ["contract", "constraint", "detail"]
                },
                "scope": {
                  "type": "string",
                  "enum": ["unit", "integration", "e2e"],
                  "description": "Test pyramid level - where this test sits in the testing hierarchy"
                },
                "intent": { "type": "string" },
                "tests": {
                  "type": "array",
                  "items": {
                    "oneOf": [
                      { "$ref": "#/definitions/GenericTest" },
                      { "$ref": "#/definitions/PHPUnitTest" }
                    ]
                  },
                  "minItems": 1
                }
              },
              "required": ["id", "acceptanceCriteria", "criticality", "scope", "intent", "tests"]
            }
          }
        }
      }
    }
  },
  "definitions": {
    "GenericTest": {
      "type": "object",
      "properties": {
        "type": { "const": "generic" },
        "command": { "type": "string" },
        "environment": {
          "type": "object",
          "additionalProperties": { "type": "string" }
        },
        "workingDirectory": { "type": "string" },
        "timeout": { "type": "number" }
      },
      "required": ["type", "command"]
    },
    "PHPUnitTest": {
      "type": "object",
      "properties": {
        "type": { "const": "phpunit" },
        "target": { "type": "string" },
        "filter": { "type": "string" },
        "group": {
          "oneOf": [
            { "type": "string" },
            { "type": "array", "items": { "type": "string" } }
          ]
        },
        "excludeGroup": {
          "oneOf": [
            { "type": "string" },
            { "type": "array", "items": { "type": "string" } }
          ]
        },
        "testsuite": {
          "oneOf": [
            { "type": "string" },
            { "type": "array", "items": { "type": "string" } }
          ]
        },
        "excludeTestsuite": {
          "oneOf": [
            { "type": "string" },
            { "type": "array", "items": { "type": "string" } }
          ]
        },
        "configuration": { "type": "string" },
        "noConfiguration": { "type": "boolean" },
        "bootstrap": { "type": "string" },
        "phpIni": {
          "type": "object",
          "additionalProperties": { "type": "string" }
        },
        "stopOnDefect": { "type": "boolean" },
        "stopOnFailure": { "type": "boolean" },
        "stopOnError": { "type": "boolean" },
        "stopOnWarning": { "type": "boolean" },
        "failOnWarning": { "type": "boolean" },
        "failOnRisky": { "type": "boolean" },
        "colors": {
          "type": "string",
          "enum": ["auto", "always", "never"]
        },
        "testdox": { "type": "boolean" },
        "verbose": {
          "type": "number",
          "enum": [0, 1, 2, 3]
        },
        "noProgress": { "type": "boolean" },
        "coverageHtml": { "type": "string" },
        "coverageClover": { "type": "string" },
        "coverageXml": { "type": "string" },
        "coverageText": {
          "oneOf": [
            { "type": "boolean" },
            { "type": "string" }
          ]
        },
        "noCoverage": { "type": "boolean" },
        "environment": {
          "type": "object",
          "additionalProperties": { "type": "string" }
        },
        "processIsolation": { "type": "boolean" },
        "noExtensions": { "type": "boolean" },
        "timeout": { "type": "number" },
        "workingDirectory": { "type": "string" }
      },
      "required": ["type"],
      "anyOf": [
        { "required": ["target"] },
        { "required": ["filter"] },
        { "required": ["group"] },
        { "required": ["testsuite"] }
      ]
    }
  }
}
```

### 5. Migration Strategy

**MANDATE**: All existing spec files and test runner infrastructure must be migrated to the new format. This is not optional.

#### 5.1 Migration Timeline

- **Day 1-2**: Archive existing specs to `specs.old/`, select ONE spec for prototyping, update to new format
- **Day 3-5**: Implement new Holon-based runner (NO legacy support)
- **Day 6-7**: Validate new runner with single spec
- **Day 8-10**: Bulk migrate all specs from `specs.old/` → `specs/`, generate spec IDs, add `#[Ticket]` attributes
- **Day 11-14**: Update composer scripts, CI/CD, documentation, archive old runners

#### 4.2 Clean Slate Approach

The new runner will **NOT support legacy format**. Clean slate benefits:

1. Simpler implementation (no dual-format parsing logic)
2. No technical debt from legacy support
3. Faster development (build for new format only)
4. Cleaner codebase without compatibility layers

### 6. Migration Examples

#### 6.1 Simple Test Migration

**Before:**
```yaml
features:
  - name: state-management
    specs:
      - acceptanceCriteria: A region tracks its current state
        criticality: contract
        intent: Provides runtime visibility into the state machine's current state
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Core/Region/CurrentStateTrackingTest.php
```

**After:**
```yaml
features:
  - name: state-management
    specs:
      - id: state-management:region-tracks-current-state
        acceptanceCriteria: A region tracks its current state
        criticality: contract
        scope: unit
        intent: Provides runtime visibility into the state machine's current state
        tests:
          - type: phpunit
            target: tests/PHPUnit/Unit/Core/Region/CurrentStateTrackingTest.php
```

#### 6.2 Test with Filters Migration

**Before:**
```yaml
specs:
  - acceptanceCriteria: Memoization prevents repeated calculations
    criticality: detail
    intent: Eliminates redundant computations
    test: vendor/bin/phpunit --filter "ChainTest::testMemoizationCachesResults"
```

**After:**
```yaml
specs:
  - id: chain:memoization-caches-results
    acceptanceCriteria: Memoization prevents repeated calculations
    criticality: detail
    scope: unit
    intent: Eliminates redundant computations
    tests:
      - type: phpunit
        filter: "ChainTest::testMemoizationCachesResults"
```

#### 6.3 Complex Command Migration

**Before:**
```yaml
specs:
  - acceptanceCriteria: Services are created lazily
    criticality: detail
    intent: Minimizes initialization overhead
    test: XDEBUG_MODE=coverage vendor/bin/phpunit --filter "ChainMailTest::memo" --testdox
```

**After:**
```yaml
specs:
  - id: chainmail:lazy-service-creation
    acceptanceCriteria: Services are created lazily
    criticality: detail
    scope: unit
    intent: Minimizes initialization overhead
    tests:
      - type: phpunit
        filter: "ChainMailTest::memo"
        testdox: true
        environment:
          XDEBUG_MODE: coverage
```

#### 6.4 Spec ID Generation Strategy

IDs should be generated from acceptance criteria using these rules:

1. **Start with feature name** (mandatory)
2. Convert to lowercase
3. Remove articles (a, an, the)
4. Extract key nouns and verbs
5. Join with hyphens
6. Limit to 70 characters (increased to accommodate feature prefix)
7. Ensure global uniqueness

**Format**: `{feature-name}:{descriptive-slug}`

**Examples**:

| Feature | Acceptance Criteria | Generated ID |
|---------|---------------------|--------------|
| state-management | A region tracks its current state | `state-management:region-tracks-current-state` |
| chain | Chain supports linking multiple middleware | `chain:supports-linking-middleware` |
| chain | Middleware executes in reverse order | `chain:middleware-executes-reverse-order` |
| chainmail | ChainMail automatically resolves dependencies | `chainmail:auto-resolve-dependencies` |
| mesh | Mesh implements ArrayAccess interface | `mesh:implements-array-access` |

**Why Colon Separator?**
- **Prevents collisions**: Different features can have similar spec names
- **Clear ownership**: Immediately know which feature a spec belongs to
- **Easy validation**: Simple split on : ensures compliance
- **Future-proof**: Scales as features are added/renamed

**Collision Handling**:
If ID already exists (very rare with feature prefix), append counter:
- `chain:links-middleware` → `chain:links-middleware-2`

#### 6.5 Multiple Tests (Future-Proofing)

While current policy mandates 1:1 spec-to-test mapping, the schema supports this:

```yaml
specs:
  - id: feature:cross-platform-compatibility
    acceptanceCriteria: Feature works across environments
    criticality: contract
    scope: integration
    intent: Validates cross-platform compatibility
    tests:
      - type: phpunit
        target: tests/PHPUnit/Unit/Feature/CoreTest.php
        environment:
          PLATFORM: linux
      - type: phpunit
        target: tests/PHPUnit/Unit/Feature/CoreTest.php
        environment:
          PLATFORM: windows
```

**Note**: This would currently violate project policy but demonstrates schema flexibility.

### 7. Automated Migration Tool

A migration script will be provided to automate the conversion:

```bash
# Analyze specs and suggest migrations
php bin/migrate-specs.php analyze specs/

# Perform migration with dry-run
php bin/migrate-specs.php migrate specs/ --dry-run

# Perform actual migration
php bin/migrate-specs.php migrate specs/

# Validate migrated specs
php bin/migrate-specs.php validate specs/
```

#### Migration Tool Logic

```php
<?php

class SpecMigrator
{
    private array $usedIds = [];

    public function migrateSpec(array $spec): array
    {
        if (!isset($spec['features'])) {
            return $spec;
        }

        foreach ($spec['features'] as &$feature) {
            foreach ($feature['specs'] as &$item) {
                // Generate ID if not present
                if (!isset($item['id'])) {
                    $item['id'] = $this->generateSpecId(
                        $item['acceptanceCriteria'],
                        $feature['name']
                    );
                }

                // Migrate test format
                if (isset($item['test'])) {
                    // Legacy string format
                    $item['tests'] = [$this->parseTestCommand($item['test'])];
                    unset($item['test']);
                }
            }
        }

        return $spec;
    }

    private function generateSpecId(string $acceptanceCriteria, string $featureName): string
    {
        // Normalize feature name
        $featureSlug = strtolower($featureName);
        $featureSlug = preg_replace('/[^a-z0-9-]/', '-', $featureSlug);
        $featureSlug = trim($featureSlug, '-');

        // Convert acceptance criteria to lowercase
        $criteria = strtolower($acceptanceCriteria);

        // Remove articles and common words
        $stopWords = ['a', 'an', 'the', 'is', 'are', 'can', 'be', 'to', 'for', 'with', 'from'];
        $words = explode(' ', $criteria);
        $words = array_filter($words, fn($w) => !in_array($w, $stopWords));

        // Remove special characters
        $descriptive = implode(' ', $words);
        $descriptive = preg_replace('/[^a-z0-9\s-]/', '', $descriptive);

        // Convert spaces to hyphens
        $descriptive = preg_replace('/\s+/', '-', trim($descriptive));
        $descriptive = trim($descriptive, '-');

        // Build ID with colon separator
        $id = $featureSlug . ':' . $descriptive;

        // Limit length (70 chars total)
        if (strlen($id) > 70) {
            // Reserve space for feature + colon
            $maxDescriptiveLength = 70 - strlen($featureSlug) - 1;
            $parts = explode('-', $descriptive);
            $descriptive = '';
            foreach ($parts as $part) {
                if (strlen($descriptive) + strlen($part) + 1 > $maxDescriptiveLength) {
                    break;
                }
                $descriptive .= ($descriptive ? '-' : '') . $part;
            }
            $id = $featureSlug . ':' . $descriptive;
        }

        // Ensure uniqueness (rare with feature namespace)
        $originalId = $id;
        $counter = 2;
        while (in_array($id, $this->usedIds)) {
            // Split on colon, append counter to spec part
            [$feature, $spec] = explode(':', $originalId, 2);
            $id = $feature . ':' . $spec . '-' . $counter;
            $counter++;
        }

        $this->usedIds[] = $id;
        return $id;
    }

    /**
     * Validate that spec ID uses correct format: {feature}:{spec}
     */
    private function validateSpecId(string $specId, string $featureName): bool
    {
        // Must contain exactly one colon
        if (substr_count($specId, ':') !== 1) {
            return false;
        }

        // Extract feature from ID
        [$featurePart, $specPart] = explode(':', $specId, 2);

        // Normalize expected feature name
        $expectedFeature = strtolower($featureName);
        $expectedFeature = preg_replace('/[^a-z0-9-]/', '-', $expectedFeature);
        $expectedFeature = trim($expectedFeature, '-');

        // Feature part must match
        return $featurePart === $expectedFeature && !empty($specPart);
    }

    private function parseTestCommand(string $command): array
    {
        // Parse environment variables
        $environment = [];
        if (preg_match('/^([A-Z_]+=\S+\s+)+/', $command, $matches)) {
            $envPart = trim($matches[0]);
            $command = substr($command, strlen($envPart));

            foreach (explode(' ', $envPart) as $envVar) {
                if (strpos($envVar, '=') !== false) {
                    [$key, $value] = explode('=', $envVar, 2);
                    $environment[$key] = $value;
                }
            }
        }

        // Check if it's a PHPUnit command
        if (strpos($command, 'phpunit') !== false) {
            return $this->parsePhpunitCommand($command, $environment);
        }

        // Generic fallback
        return [
            'type' => 'generic',
            'command' => trim($command),
            'environment' => $environment ?: null,
        ];
    }

    private function parsePhpunitCommand(string $command, array $environment): array
    {
        $config = [
            'type' => 'phpunit',
        ];

        if (!empty($environment)) {
            $config['environment'] = $environment;
        }

        // Parse --filter
        if (preg_match('/--filter[=\s]+"?([^"\s]+)"?/', $command, $matches)) {
            $config['filter'] = $matches[1];
        }

        // Parse --group
        if (preg_match('/--group[=\s]+"?([^"\s]+)"?/', $command, $matches)) {
            $config['group'] = $matches[1];
        }

        // Parse --testsuite
        if (preg_match('/--testsuite[=\s]+"?([^"\s]+)"?/', $command, $matches)) {
            $config['testsuite'] = $matches[1];
        }

        // Parse flags
        if (strpos($command, '--testdox') !== false) {
            $config['testdox'] = true;
        }

        if (strpos($command, '--stop-on-failure') !== false) {
            $config['stopOnFailure'] = true;
        }

        // Parse target (file/directory at the end)
        if (preg_match('/\s+(tests\/[^\s]+\.php)$/', $command, $matches)) {
            $config['target'] = $matches[1];
        } elseif (preg_match('/\s+(tests\/[^\s]+\/?)$/', $command, $matches)) {
            $config['target'] = rtrim($matches[1], '/');
        }

        return $config;
    }
}
```

### 8. Linking Tests to Specs via PHPUnit Attributes

Use PHPUnit's built-in attributes to link tests to spec IDs. Two approaches are supported:

#### 8.1 Using `#[Ticket]` Attribute (Recommended)

The `#[Ticket]` attribute is semantically appropriate for linking to external identifiers:

```php
<?php

namespace Noem\State\Test\Unit\Core\Region;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Ticket;

#[Ticket('state-management:region-tracks-current-state')]
class CurrentStateTrackingTest extends TestCase
{
    public function testRegionTracksCurrentState(): void
    {
        // Test implementation
    }
}
```

**Benefits**:
- Standard PHPUnit attribute (no custom code)
- Designed for external issue/ticket tracking
- Can be queried programmatically
- Integrates with PHPUnit's metadata system

#### 8.2 Using `#[Group]` Attribute (Alternative)

Alternatively, use `#[Group]` with a `spec:` prefix:

```php
<?php

namespace Noem\State\Test\Unit\Core\Region;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('spec:state-management:region-tracks-current-state')]
class CurrentStateTrackingTest extends TestCase
{
    public function testRegionTracksCurrentState(): void
    {
        // Test implementation
    }
}
```

**Benefits**:
- Can filter tests by spec: `phpunit --group spec:state-management:region-tracks-current-state`
- Can filter by feature: `phpunit --group spec:state-management:*` (with wildcard support)
- Combines with other groups: `#[Group('unit')] #[Group('spec:..')]`
- More flexible for test selection

**Recommendation**: Use `#[Ticket]` as it's more semantically correct for spec ID linking.

**Validation**: The migration tool will verify that all spec IDs start with their feature name to prevent collisions.

#### 8.3 Validation Tooling

**Spec Coverage Validator**:

```php
<?php

use PHPUnit\Framework\TestSuite;
use PHPUnit\TextUI\Configuration\Registry as ConfigurationRegistry;
use PHPUnit\TextUI\TestSuiteMapper;
use Symfony\Component\Yaml\Yaml;

/**
 * Scan tests and verify spec ID coverage using PHPUnit attributes
 */
class SpecCoverageValidator
{
    public function validateCoverage(string $specFile, string $testDir): array
    {
        $spec = Yaml::parseFile($specFile);
        $specIds = $this->extractSpecIds($spec);
        $testIds = $this->scanTestTickets($testDir);

        $missing = array_diff($specIds, $testIds);
        $orphaned = array_diff($testIds, $specIds);

        return [
            'total_specs' => count($specIds),
            'covered_specs' => count(array_intersect($specIds, $testIds)),
            'missing_tests' => $missing,
            'orphaned_tests' => $orphaned,
            'coverage_percent' => count($specIds) > 0
                ? (count($specIds) - count($missing)) / count($specIds) * 100
                : 0,
        ];
    }

    private function extractSpecIds(array $spec): array
    {
        $ids = [];
        foreach ($spec['features'] as $feature) {
            foreach ($feature['specs'] as $s) {
                $ids[] = $s['id'];
            }
        }
        return $ids;
    }

    private function scanTestTickets(string $dir): array
    {
        $ids = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            // Match #[Ticket('spec-id')]
            if (preg_match('/#\[Ticket\([\'"]([a-z0-9-]+)[\'"]\)\]/', $content, $matches)) {
                $ids[] = $matches[1];
            }

            // Also match #[Group('spec:spec-id')]
            if (preg_match('/#\[Group\([\'"]spec:([a-z0-9-]+)[\'"]\)\]/', $content, $matches)) {
                $ids[] = $matches[1];
            }
        }

        return array_unique($ids);
    }

    /**
     * Alternative: Use PHPUnit reflection to scan test suite
     */
    private function scanTestTicketsViaReflection(TestSuite $suite): array
    {
        $ids = [];

        foreach ($suite->tests() as $test) {
            if ($test instanceof TestSuite) {
                $ids = array_merge($ids, $this->scanTestTicketsViaReflection($test));
                continue;
            }

            $reflection = new ReflectionClass($test);

            // Check for Ticket attribute
            $attributes = $reflection->getAttributes(\PHPUnit\Framework\Attributes\Ticket::class);
            foreach ($attributes as $attribute) {
                $ids[] = $attribute->getArguments()[0];
            }

            // Check for Group attribute with spec: prefix
            $groupAttributes = $reflection->getAttributes(\PHPUnit\Framework\Attributes\Group::class);
            foreach ($groupAttributes as $attribute) {
                $group = $attribute->getArguments()[0];
                if (str_starts_with($group, 'spec:')) {
                    $ids[] = substr($group, 5);
                }
            }
        }

        return array_unique($ids);
    }
}
```

**CLI Tool**:

```bash
# Validate spec coverage
php bin/spec-coverage.php validate specs/core/region.yaml tests/PHPUnit/Unit/Core/Region/

# Generate report
php bin/spec-coverage.php report specs/ tests/PHPUnit/

# List uncovered specs
php bin/spec-coverage.php missing specs/chain/middleware.yaml tests/PHPUnit/Unit/Middleware/
```

### 9. Test Runner Migration

The project has two state machine-based test runners that must be migrated:

1. **Sequential Runner**: `machines/middleware-test-runner/machine.php`
2. **Parallel Runner**: `machines/middleware-test-runner-parallel/machine.php`

Both runners currently:
- Read spec YAML files containing `test: "command string"`
- Execute commands directly with `exec()`
- Collect and display results

#### 7.1 Current Implementation (Both Runners)

Both runners have code like this:

```php
// machines/middleware-test-runner/machine.php (line 336)
// machines/middleware-test-runner-parallel/machine.php (lines 311, 398, 600)

foreach ($feature['specs'] as $spec) {
    // Current: Read 'test' as string
    $command = "cd /var/www/html && " . $spec['test'] . " 2>&1";
    exec($command, $output, $returnCode);

    $testResult = [
        'feature' => $feature['name'],
        'acceptanceCriteria' => $spec['acceptanceCriteria'],
        'test' => $spec['test'],  // Store original command
        'passed' => $returnCode === 0,
        'returnCode' => $returnCode,
        'output' => implode("\n", $output),
        'duration' => $duration,
        'timestamp' => date('Y-m-d H:i:s'),
    ];
}
```

#### 7.2 Required Migration Changes

Each runner needs to:
1. Read `spec['tests']` (array) instead of `spec['test']` (string)
2. Handle first item in array (enforcing 1:1 policy)
3. Build commands based on test type
4. Apply environment variables
5. Store structured test configuration

#### 7.3 Command Builder Functions

Add these helper functions to both test runner files:

```php
/**
 * Build command from test configuration
 */
function buildTestCommand(array $testConfig): string
{
    $type = $testConfig['type'] ?? 'generic';

    if ($type === 'phpunit') {
        return buildPhpunitCommand($testConfig);
    }

    if ($type === 'generic') {
        return $testConfig['command'];
    }

    throw new RuntimeException("Unknown test type: {$type}");
}

/**
 * Build PHPUnit command from configuration
 */
function buildPhpunitCommand(array $config): string
{
    $parts = ['vendor/bin/phpunit'];

    // Selection (at least one required)
    if (!empty($config['target'])) {
        $parts[] = escapeshellarg($config['target']);
    }

    if (!empty($config['filter'])) {
        $parts[] = '--filter=' . escapeshellarg($config['filter']);
    }

    if (!empty($config['group'])) {
        $groups = is_array($config['group']) ? $config['group'] : [$config['group']];
        foreach ($groups as $group) {
            $parts[] = '--group=' . escapeshellarg($group);
        }
    }

    if (!empty($config['excludeGroup'])) {
        $groups = is_array($config['excludeGroup'])
            ? $config['excludeGroup']
            : [$config['excludeGroup']];
        foreach ($groups as $group) {
            $parts[] = '--exclude-group=' . escapeshellarg($group);
        }
    }

    if (!empty($config['testsuite'])) {
        $suites = is_array($config['testsuite'])
            ? $config['testsuite']
            : [$config['testsuite']];
        foreach ($suites as $suite) {
            $parts[] = '--testsuite=' . escapeshellarg($suite);
        }
    }

    // Configuration
    if (!empty($config['configuration'])) {
        $parts[] = '-c ' . escapeshellarg($config['configuration']);
    }

    if (!empty($config['noConfiguration'])) {
        $parts[] = '--no-configuration';
    }

    if (!empty($config['bootstrap'])) {
        $parts[] = '--bootstrap=' . escapeshellarg($config['bootstrap']);
    }

    // Execution control
    if (!empty($config['stopOnDefect'])) {
        $parts[] = '--stop-on-defect';
    }

    if (!empty($config['stopOnFailure'])) {
        $parts[] = '--stop-on-failure';
    }

    if (!empty($config['stopOnError'])) {
        $parts[] = '--stop-on-error';
    }

    if (!empty($config['failOnWarning'])) {
        $parts[] = '--fail-on-warning';
    }

    if (!empty($config['failOnRisky'])) {
        $parts[] = '--fail-on-risky';
    }

    // Reporting
    if (!empty($config['colors'])) {
        $parts[] = '--colors=' . $config['colors'];
    }

    if (!empty($config['testdox'])) {
        $parts[] = '--testdox';
    }

    if (isset($config['verbose']) && $config['verbose'] > 0) {
        $parts[] = str_repeat('-v', min($config['verbose'], 3));
    }

    if (!empty($config['noProgress'])) {
        $parts[] = '--no-progress';
    }

    // Coverage
    if (!empty($config['coverageHtml'])) {
        $parts[] = '--coverage-html=' . escapeshellarg($config['coverageHtml']);
    }

    if (!empty($config['coverageClover'])) {
        $parts[] = '--coverage-clover=' . escapeshellarg($config['coverageClover']);
    }

    if (!empty($config['coverageXml'])) {
        $parts[] = '--coverage-xml=' . escapeshellarg($config['coverageXml']);
    }

    if (isset($config['coverageText'])) {
        if ($config['coverageText'] === true) {
            $parts[] = '--coverage-text';
        } elseif (is_string($config['coverageText'])) {
            $parts[] = '--coverage-text=' . escapeshellarg($config['coverageText']);
        }
    }

    if (!empty($config['noCoverage'])) {
        $parts[] = '--no-coverage';
    }

    // Advanced
    if (!empty($config['processIsolation'])) {
        $parts[] = '--process-isolation';
    }

    if (!empty($config['noExtensions'])) {
        $parts[] = '--no-extensions';
    }

    // PHP ini directives
    if (!empty($config['phpIni'])) {
        foreach ($config['phpIni'] as $key => $value) {
            $parts[] = '-d ' . escapeshellarg("{$key}={$value}");
        }
    }

    return implode(' ', $parts);
}

/**
 * Apply environment variables for test execution
 */
function applyTestEnvironment(array $testConfig): void
{
    if (!empty($testConfig['environment'])) {
        foreach ($testConfig['environment'] as $key => $value) {
            putenv("{$key}={$value}");
        }
    }
}

/**
 * Get display command from test configuration
 */
function getTestDisplayCommand(array $testConfig): string
{
    return buildTestCommand($testConfig);
}
```

#### 7.4 Migration for Sequential Runner

**File**: `machines/middleware-test-runner/machine.php`

**Changes Required**:

1. **Line 336** (command execution):
```php
// BEFORE:
$command = "cd /var/www/html && " . $spec['test'] . " 2>&1";

// AFTER:
if (!isset($spec['tests']) || empty($spec['tests'])) {
    throw new RuntimeException(
        "Spec missing 'tests' field: {$spec['acceptanceCriteria']}"
    );
}

$testConfig = $spec['tests'][0];  // Enforce 1:1 policy
applyTestEnvironment($testConfig);
$command = "cd /var/www/html && " . buildTestCommand($testConfig) . " 2>&1";
```

2. **Line 327** (display command):
```php
// BEFORE:
echo "Command: {$spec['test']}\n";

// AFTER:
$displayCommand = getTestDisplayCommand($testConfig);
echo "Command: {$displayCommand}\n";
```

3. **Line 345** (store test info):
```php
// BEFORE:
'test' => $spec['test'],

// AFTER:
'test' => buildTestCommand($testConfig),
'testConfig' => $testConfig,
```

#### 7.5 Migration for Parallel Runner

**File**: `machines/middleware-test-runner-parallel/machine.php`

**Changes Required**:

1. **Line 311** (runFeatureTests function):
```php
// BEFORE:
foreach ($feature['specs'] as $spec) {
    $command = "cd /var/www/html && " . $spec['test'] . " 2>&1";

// AFTER:
foreach ($feature['specs'] as $spec) {
    if (!isset($spec['tests']) || empty($spec['tests'])) {
        throw new RuntimeException(
            "Spec missing 'tests' field: {$spec['acceptanceCriteria']}"
        );
    }

    $testConfig = $spec['tests'][0];
    applyTestEnvironment($testConfig);
    $command = "cd /var/www/html && " . buildTestCommand($testConfig) . " 2>&1";
```

2. **Lines 390-425** (embedded script in parallel execution):
```php
// Update the embedded PHP script to include command building functions
$scriptContent = <<<'PHP'
<?php
require '/var/www/html/vendor/autoload.php';

// Include buildTestCommand, buildPhpunitCommand, applyTestEnvironment functions here
function buildTestCommand(array $testConfig): string { /* ... */ }
function buildPhpunitCommand(array $config): string { /* ... */ }
function applyTestEnvironment(array $testConfig): void { /* ... */ }

function runFeatureTests(array $feature, bool $verbose): array
{
    $results = [];

    foreach ($feature['specs'] as $spec) {
        if (!isset($spec['tests']) || empty($spec['tests'])) {
            throw new RuntimeException(
                "Spec missing 'tests' field: {$spec['acceptanceCriteria']}"
            );
        }

        $startTime = microtime(true);
        $testConfig = $spec['tests'][0];

        applyTestEnvironment($testConfig);
        $command = "cd /var/www/html && " . buildTestCommand($testConfig) . " 2>&1";
        exec($command, $output, $returnCode);

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 3);

        $testResult = [
            'feature' => $feature['name'],
            'acceptanceCriteria' => $spec['acceptanceCriteria'],
            'test' => buildTestCommand($testConfig),
            'testConfig' => $testConfig,
            'passed' => $returnCode === 0,
            'returnCode' => $returnCode,
            'output' => implode("\n", $output),
            'duration' => $duration,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $results[] = $testResult;
    }

    return $results;
}

$dataFile = $argv[1];
$data = json_decode(file_get_contents($dataFile), true);
$results = runFeatureTests($data['feature'], $data['verbose']);
echo json_encode($results);
PHP;
```

3. **Line 600** (sequential fallback):
```php
// BEFORE:
$command = "cd /var/www/html && " . $spec['test'] . " 2>&1";

// AFTER:
if (!isset($spec['tests']) || empty($spec['tests'])) {
    throw new RuntimeException(
        "Spec missing 'tests' field: {$spec['acceptanceCriteria']}"
    );
}

$testConfig = $spec['tests'][0];
applyTestEnvironment($testConfig);
$command = "cd /var/www/html && " . buildTestCommand($testConfig) . " 2>&1";
```

#### 7.6 Testing the Migration

After migration, test both runners with migrated specs:

```bash
# Test sequential runner
ddev exec machines/middleware-test-runner/run.sh --spec=specs/core/region.yaml

# Test parallel runner
ddev exec machines/middleware-test-runner-parallel/run.sh --spec=specs/chain/middleware.yaml

# Test with various options
ddev exec machines/middleware-test-runner-parallel/run.sh \
  --spec=specs/features/async.yaml \
  --max-threads=6 \
  --quiet
```

Both runners should:
- Parse new `tests` array format
- Build commands correctly from structured config
- Apply environment variables
- Execute tests successfully
- Display results as before

### 10. Composer Script Integration

Add convenience scripts for running the test runners:

```json
{
  "scripts": {
    "spec:seq": "machines/middleware-test-runner/run.sh",
    "spec:parallel": "machines/middleware-test-runner-parallel/run.sh",
    "spec:migrate": "php bin/migrate-specs.php migrate specs/",
    "test": "phpunit",
    "quality": [
      "@cs:check",
      "@psalm",
      "@test"
    ]
  }
}
```

**Usage**:
```bash
# Run spec with sequential runner
ddev exec composer spec:seq -- --spec=specs/core/region.yaml

# Run spec with parallel runner
ddev exec composer spec:parallel -- --spec=specs/chain/middleware.yaml --max-threads=6

# Migrate specs
ddev exec composer spec:migrate
```

### 11. Benefits

#### 9.1 Better Control
```yaml
specs:
  - id: async:debug-mode-logging
    acceptanceCriteria: Debug mode logs async operations
    criticality: detail
    scope: unit
    intent: Enables async debugging
    tests:
      - type: phpunit
        target: tests/PHPUnit/Unit/Feature/Async/
        group: [unit, async]
        stopOnFailure: true
        testdox: true
        environment:
          ASYNC_DEBUG: "1"
```

#### 9.2 Reusability via YAML Anchors
```yaml
# Define common configuration
.phpunit-defaults: &phpunit-defaults
  type: phpunit
  colors: always
  stopOnFailure: true
  failOnWarning: true

specs:
  - id: feature:test-a
    acceptanceCriteria: Test A
    criticality: contract
    scope: unit
    intent: Validates Test A behavior
    tests:
      - <<: *phpunit-defaults
        target: tests/PHPUnit/Unit/TestA.php

  - id: feature:test-b
    acceptanceCriteria: Test B
    criticality: contract
    scope: unit
    intent: Validates Test B behavior
    tests:
      - <<: *phpunit-defaults
        target: tests/PHPUnit/Unit/TestB.php
        group: [critical]
```

#### 9.3 Schema Validation
- JSON Schema validation catches configuration errors early
- IDE autocomplete for available options
- Type-safe configuration prevents typos
- `oneOf` union ensures proper test type validation

#### 9.4 CI/CD Integration
```yaml
specs:
  - id: ci:unit-test-suite
    acceptanceCriteria: Unit test suite runs in CI
    criticality: contract
    scope: unit
    intent: Validates CI integration
    tests:
      - type: phpunit
        target: tests/PHPUnit/Unit/
        coverageClover: build/logs/clover.xml
        failOnWarning: true
        failOnRisky: true
        environment:
          CI: "true"
```

### 12. Complete Spec File Migration Example

#### Before (Current Format)

```yaml
name: region
group: core
description: The Region class is the runtime engine

features:
  - name: state-management
    specs:
      - acceptanceCriteria: A region tracks its current state
        criticality: contract
        intent: Provides runtime visibility
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Core/Region/CurrentStateTrackingTest.php

      - acceptanceCriteria: A region can check if it is in a specific state
        criticality: contract
        intent: Enables conditional logic
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Core/Region/StateCheckTest.php

  - name: integration
    specs:
      - acceptanceCriteria: Basic transition executes correctly
        criticality: contract
        intent: Validates complete workflow
        test: XDEBUG_MODE=coverage vendor/bin/phpunit tests/PHPUnit/Integration/Core/Region/BasicTransitionIntegrationTest.php --testdox

**Issues**:
- No spec IDs
- String test commands
- No structured configuration
```

#### After (New Format with Anchors)

```yaml
name: region
group: core
description: The Region class is the runtime engine

# Reusable configuration
.test-defaults: &test-defaults
  type: phpunit
  colors: always
  stopOnFailure: true
  environment:
    APP_ENV: test

.test-with-coverage: &test-with-coverage
  <<: *test-defaults
  environment:
    APP_ENV: test
    XDEBUG_MODE: coverage

features:
  - name: state-management
    specs:
      - id: state-management:region-tracks-current-state
        acceptanceCriteria: A region tracks its current state
        criticality: contract
        scope: unit
        intent: Provides runtime visibility
        tests:
          - <<: *test-defaults
            target: tests/PHPUnit/Unit/Core/Region/CurrentStateTrackingTest.php
            group: [unit, core, state-management]

      - id: state-management:region-checks-specific-state
        acceptanceCriteria: A region can check if it is in a specific state
        criticality: contract
        scope: unit
        intent: Enables conditional logic
        tests:
          - <<: *test-defaults
            target: tests/PHPUnit/Unit/Core/Region/StateCheckTest.php
            group: [unit, core, state-management]

  - name: integration
    specs:
      - id: integration:basic-transition-workflow
        acceptanceCriteria: Basic transition executes correctly
        criticality: contract
        scope: integration
        intent: Validates complete workflow
        tests:
          - <<: *test-with-coverage
            target: tests/PHPUnit/Integration/Core/Region/BasicTransitionIntegrationTest.php
            group: [integration, core]
            testdox: true
            coverageClover: build/coverage/region-integration.xml
```

**Improvements**:
- ✅ Spec IDs added
- ✅ Structured test configuration
- ✅ YAML anchors for reusability
- ✅ Environment variables properly structured
- ✅ Coverage configuration explicit

### 13. Migration Checklist

#### Phase 1: Preparation (Week 1)
- [ ] Implement JSON Schema for new format
- [ ] Create migration tool (`bin/migrate-specs.php`)
- [ ] Create validation tool
- [ ] Implement new `TestExecutor` interface
- [ ] Implement `PHPUnitTestExecutor`
- [ ] Implement `GenericTestExecutor`
- [ ] Update spec parser to support both formats temporarily

#### Phase 2: Spec Migration (Week 2)
- [ ] Run migration tool on all spec files
- [ ] Generate spec IDs with feature prefix for all specs
- [ ] Manual review of automated migrations
- [ ] Verify ID uniqueness and feature prefix compliance
- [ ] Validate spec IDs start with correct feature name
- [ ] Fix any migration issues
- [ ] Validate all migrated specs
- [ ] Commit migrated specs

#### Phase 3: Test Runner Migration (Week 3)
- [ ] Add `#[Ticket]` or `#[Group('spec:*')]` attributes to all test classes
- [ ] Update test runners to handle new format
- [ ] Update composer scripts
- [ ] Create spec coverage validation tool
- [ ] Update CI/CD pipelines to run coverage checks
- [ ] Test all spec executions
- [ ] Verify 100% spec-to-test coverage
- [ ] Fix any execution issues

#### Phase 4: Documentation (Week 4)
- [ ] Update CLAUDE.md with new format
- [ ] Update spec-planner agent
- [ ] Update core-development-expert agent
- [ ] Create migration guide
- [ ] Update examples and templates

#### Phase 5: Cleanup (Week 5)
- [ ] Archive old runners to `machines.old/` (or delete if agreed)
- [ ] Remove temporary `specs.old/` backup directory (or keep as reference)
- [ ] Final validation of all specs
- [ ] Archive migration tools for reference

### 14. Success Metrics

1. **Migration Completeness**: 100% of specs migrated (mandatory)
2. **Validation Coverage**: 100% of specs pass JSON Schema validation
3. **Test Execution**: 100% of migrated specs execute successfully
4. **Zero Regressions**: All tests that passed before migration still pass
5. **Schema Adoption**: All new specs use structured format
6. **Tooling Support**: IDE autocomplete and validation working

## Migrating Test Runners to Bleeding-Edge Holons

### Reference Documentation

**Authoritative Source**: `machines/machine-agent/holon-spec.yaml`

This file contains the complete, up-to-date specification for Holon YAML format and capabilities. All migration work MUST reference this file as the source of truth for:
- Feature ordering requirements (ExtendedState must be first)
- Context API methods (`$this->get()`, `$this->set()`)
- Callback signature patterns (module-level functions returning closures)
- Guard return type requirements (must return bool)
- Critical do's and don'ts

### Current State

The middleware test runners (`machines/middleware-test-runner/` and `machines/middleware-test-runner-parallel/`) are legacy PHP state machines that:

1. **Use procedural PHP**: Direct `exec()` calls, global variables, class properties
2. **Lack ExtendedState**: No `$this->` context API for clean state management
3. **Hard-coded string parsing**: Expect simple `test:` string, not structured `tests:` arrays
4. **Legacy structure**: `machine.php` + `machine.yml` split instead of unified Holon
5. **Embedded logic**: Test execution logic mixed into PHP file instead of module-level functions

**Goal**: Modernize structure while preserving existing CLI interface and execution mechanics.

**IMPORTANT**: This is a **NEW machine implementation**, NOT an in-place modification. The existing test runners (`machines/middleware-test-runner/` and `machines/middleware-test-runner-parallel/`) remain as legacy reference, but will be **incompatible with the new spec format** once `test:` is migrated to `tests:`. The old runners can remain in the codebase as reference, but are not expected to work with migrated spec files.

### Migration Goals

Create a NEW test runner machine (`middleware-test-runner-holon/`) that:

1. **Leverage ExtendedState**: Use `$this->get()`, `$this->set()` for cleaner state management
2. **Parse structured tests**: Read and execute new `tests:` arrays from spec files
3. **Self-contained**: Single `holon.yml` file with inline or separate functions
4. **Type-safe callbacks**: Module-level functions returning closures
5. **Follow best practices**: Feature ordering, proper callback patterns
6. **Maintain existing functionality**: Same CLI interface, same parallel/sequential behavior

**Note**: Advanced features like Interactions, Abilities, and Presentations are **not needed** for this use case. The migration focuses on modernizing the codebase structure while preserving existing test execution mechanics.

### Required Features (minimal set)

Per `holon-spec.yaml`, features MUST be loaded in this order:

```yaml
machine:
  features:
    # CRITICAL: Order matters!
    - class: Noem\State\Feature\ExtendedState\ExtendedState  # MUST be first
    - class: Noem\State\Feature\RegionLoader\RegionLoader    # For loading spec files (optional)
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature # For validating spec structure (optional)
```

### Key Capabilities to Leverage

#### Context API (`$this->`)

**From holon-spec.yaml, lines 389-405**

ExtendedState provides clean state management via context helpers:

```php
// State management - replaces global variables and class properties
$this->get('key', $default)
$this->set('key', $value)

// Example: Track test execution state
$this->set('tests_passed', 0);
$this->set('tests_failed', 0);
$this->set('current_test_index', 0);

// Retrieve with defaults
$passed = $this->get('tests_passed', 0);
$currentIndex = $this->get('current_test_index', 0);
```

**Benefits**:
- No global variables
- No class property management
- Clean separation of concerns
- Easy to test (can inject mock context)

### Migration Steps

#### Step 1: Create Unified Holon Structure

**Target**: Single `holon.yml` file (or `holon.yml` + `holon-functions.php`)

```yaml
# machines/middleware-test-runner-holon/holon.yml

machine:
  require: !php require '/var/www/html/machines/middleware-test-runner-holon/holon-functions.php'

  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState  # Core state management

  eventLoop:
    autoRun: false  # CLI will control execution
    maxIterations: 0

states:
  - name: initializing
    onEnter:
      - run: !php return onEnterInitializing()
    transitions:
      - target: loading_spec
        guard: !php return guardInitialized()

  - name: loading_spec
    action:
      - run: !php return actionLoadSpec()
        async:
          enabled: true
    transitions:
      - target: running_tests
        guard: !php return guardSpecLoaded()
      - target: error
        guard: !php return guardLoadError()

  - name: running_tests
    action:
      - run: !php return actionRunTests()
        async:
          enabled: true
    transitions:
      - target: summarizing
        guard: !php return guardAllTestsComplete()
      - target: stopping
        guard: !php return guardStopRequested()

  - name: stopping
    onEnter:
      - run: !php return onEnterStopping()
    transitions:
      - target: summarizing

  - name: summarizing
    action:
      - run: !php return actionSummarize()
    transitions:
      - target: complete

  - name: error
    onEnter:
      - run: !php return onEnterError()
    transitions:
      - target: complete

  - name: complete

initial: initializing
final: complete
```

#### Step 2: Implement Module-Level Functions

**Target**: `holon-functions.php` following patterns from `holon-spec.yaml`

```php
<?php
// machines/middleware-test-runner-holon/holon-functions.php

/**
 * Initialize test runner
 * Parses CLI args and sets up initial state
 */
function onEnterInitializing() {
    return function(object $t): void {
        // Parse CLI arguments
        global $argv;
        $specPath = null;
        $stopOnFailure = false;

        foreach ($argv as $i => $arg) {
            if ($arg === '--spec' && isset($argv[$i + 1])) {
                $specPath = $argv[$i + 1];
            }
            if ($arg === '--stop-on-failure') {
                $stopOnFailure = true;
            }
        }

        // Store in context (replaces global variables)
        $this->set('spec_path', $specPath);
        $this->set('stop_on_failure', $stopOnFailure);
        $this->set('initialized', true);

        echo "Test Runner initialized\n";
    };
}

/**
 * Guard: Check if initialization complete
 */
function guardInitialized() {
    return function(object $t): bool {
        return $this->get('initialized') === true;
    };
}

/**
 * Action: Load spec file and parse tests
 */
function actionLoadSpec() {
    return function(object $t): \Generator {
        $specPath = $this->get('spec_path');

        if (!$specPath || !file_exists($specPath)) {
            $this->set('load_error', 'Spec file not found');
            yield;
            return;
        }

        // Parse YAML spec
        $specContent = file_get_contents($specPath);
        $spec = yaml_parse($specContent);

        if (!$spec) {
            $this->set('load_error', 'Invalid YAML');
            yield;
            return;
        }

        // Extract tests from new format
        $tests = [];
        foreach ($spec['features'] ?? [] as $feature) {
            foreach ($feature['specs'] ?? [] as $specDef) {
                // Parse tests array
                $specTests = $specDef['tests'] ?? [];
                foreach ($specTests as $testConfig) {
                    $tests[] = [
                        'spec_id' => $specDef['id'],
                        'acceptance_criteria' => $specDef['acceptanceCriteria'],
                        'config' => $testConfig
                    ];
                }
            }
        }

        $this->set('tests', $tests);
        $this->set('tests_total', count($tests));
        $this->set('current_test_index', 0);
        $this->set('spec_loaded', true);

        echo "Loaded " . count($tests) . " tests from spec\n";
        yield;
    };
}

/**
 * Guard: Check if spec loaded successfully
 */
function guardSpecLoaded() {
    return function(object $t): bool {
        return $this->get('spec_loaded') === true;
    };
}

/**
 * Guard: Check if spec load failed
 */
function guardLoadError() {
    return function(object $t): bool {
        return $this->get('load_error') !== null;
    };
}

/**
 * Action: Run tests from spec
 *
 * Note: Not actually async in this version, but demonstrates the pattern.
 * Could be made async if needed for responsive CLI or parallel execution.
 */
function actionRunTests() {
    return function(object $t): void {
        $tests = $this->get('tests', []);
        $currentIndex = $this->get('current_test_index', 0);
        $stopOnFailure = $this->get('stop_on_failure', false);

        // Initialize counters on first run
        if (!$this->get('tests_passed')) {
            $this->set('tests_passed', 0);
            $this->set('tests_failed', 0);
        }

        // Process current test
        if ($currentIndex < count($tests)) {
            $test = $tests[$currentIndex];

            echo "Running: {$test['spec_id']}\n";

            // Build command from NEW structured test config
            $command = buildTestCommand($test['config']);

            // Execute test
            exec($command . ' 2>&1', $output, $exitCode);

            // Record result using context
            if ($exitCode === 0) {
                $passed = $this->get('tests_passed', 0);
                $this->set('tests_passed', $passed + 1);
                echo "  ✓ PASS\n";
            } else {
                $failed = $this->get('tests_failed', 0);
                $this->set('tests_failed', $failed + 1);
                echo "  ✗ FAIL\n";

                // Check stop-on-failure flag
                if ($stopOnFailure) {
                    $this->set('stop_requested', true);
                }
            }

            // Move to next test
            $this->set('current_test_index', $currentIndex + 1);
        } else {
            // All tests complete
            $this->set('all_tests_complete', true);
        }
    };
}

/**
 * Build PHPUnit command from test config
 */
function buildTestCommand(array $config): string {
    $type = $config['type'] ?? 'generic';

    if ($type === 'phpunit') {
        $cmd = 'cd /var/www/html && vendor/bin/phpunit';

        // Add target
        if (isset($config['target'])) {
            $cmd .= ' ' . escapeshellarg($config['target']);
        }

        // Add options
        if (isset($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                if ($value === true) {
                    $cmd .= ' --' . $key;
                } elseif ($value !== false && $value !== null) {
                    $cmd .= ' --' . $key . '=' . escapeshellarg($value);
                }
            }
        }

        return $cmd;
    } elseif ($type === 'generic') {
        // Generic shell command
        $cmd = 'cd /var/www/html && ' . $config['command'];

        // Add environment variables
        if (isset($config['environment'])) {
            $envVars = '';
            foreach ($config['environment'] as $key => $value) {
                $envVars .= escapeshellarg($key) . '=' . escapeshellarg($value) . ' ';
            }
            $cmd = $envVars . $cmd;
        }

        return $cmd;
    }

    throw new \RuntimeException("Unknown test type: {$type}");
}

/**
 * Guard: All tests complete
 */
function guardAllTestsComplete() {
    return function(object $t): bool {
        return $this->get('all_tests_complete') === true;
    };
}

/**
 * Guard: Stop requested due to failure
 */
function guardStopRequested() {
    return function(object $t): bool {
        return $this->get('stop_requested') === true;
    };
}

/**
 * On enter stopping state
 */
function onEnterStopping() {
    return function(object $t): void {
        echo "Stopping due to failure (--stop-on-failure)\n";
    };
}

/**
 * Action: Output summary
 */
function actionSummarize() {
    return function(object $t): void {
        $passed = $this->get('tests_passed', 0);
        $failed = $this->get('tests_failed', 0);
        $total = $this->get('tests_total', 0);

        echo "\n========================================\n";
        echo "Test Summary\n";
        echo "========================================\n";
        echo "Total:  {$total}\n";
        echo "Passed: {$passed}\n";
        echo "Failed: {$failed}\n";
        echo "========================================\n";
    };
}

/**
 * On enter error state
 */
function onEnterError() {
    return function(object $t): void {
        $error = $this->get('load_error', 'Unknown error');
        echo "ERROR: {$error}\n";
    };
}
```

#### Step 3: Create CLI Entry Point

```php
#!/usr/bin/env php
<?php
// machines/middleware-test-runner-holon/run.php

require_once __DIR__ . '/../../vendor/autoload.php';

use Noem\State\Holon;
use Noem\State\StandardRuntime;

$holon = Holon::fromYaml(file_get_contents(__DIR__ . '/holon.yml'));
$region = $holon->build();

$runtime = new StandardRuntime($region);
$runtime->run();

// Extract exit code
$exitCode = 0;
$region->trigger((object)[
    'extract' => function() use (&$exitCode) {
        $failed = $this->get('tests_failed', 0);
        $exitCode = $failed > 0 ? 1 : 0;
    }
]);

exit($exitCode);
```

### Benefits of Holon Migration

1. **Cleaner State Management**: ExtendedState replaces global variables and class properties with context helpers
2. **Self-Contained Structure**: Single `holon.yml` file with module-level functions, no procedural PHP
3. **Structured Test Parsing**: Native support for new `tests:` array format from spec files
4. **Type-Safe Callbacks**: Module-level functions returning closures with proper type hints
5. **Maintainability**: Clear state machine structure vs. embedded procedural logic
6. **Extensibility**: Easy to add features later (async, validation, etc.) without architecture changes
7. **Testability**: Can mock context helpers and test state transitions in isolation
8. **Consistency**: Same patterns as other Holon-based machines in the project

### Critical Requirements

Per `holon-spec.yaml`:

✅ **DO**:
- Load ExtendedState FIRST (if using multiple features)
- Use module-level functions returning closures (NOT class methods)
- Guards MUST return bool explicitly (`return $this->get('done') === true`)
- Use `$this->get()` and `$this->set()` for state management
- Follow callback signature pattern: `function name() { return function(object $t): ReturnType { /* code */ }; }`

❌ **DON'T**:
- Use class methods for callbacks
- Return non-boolean from guards
- Use global variables (use context instead)
- Mix procedural and state machine approaches

### Migration Strategy

**Clean Slate Approach** - No backward compatibility, no dual-format parser:

1. **Archive Existing Specs**: Move `specs/` → `specs.old/` (preserve existing specs)
2. **Single Spec Prototype**: Copy ONE spec file to new `specs/` directory, update to new `tests:` format
3. **Build New Runner**: Implement `machines/middleware-test-runner-holon/` to work with new format ONLY
4. **Validate with One Spec**: Test new runner with the single migrated spec until working correctly
5. **Bulk Migration**: Migrate all remaining specs from `specs.old/` → `specs/` with new format
6. **Update Composer**: Point composer scripts to new runner
7. **Archive Legacy**: Move old runners to `machines.old/` or mark as legacy reference

**Critical Notes**:
- **No dual-format support**: New runner ONLY supports `tests:` format (not `test:` strings)
- **No backward compatibility**: Old specs in `specs.old/` won't work until migrated
- **Clean implementation**: Build for new format from scratch, simpler codebase
- Parallel execution mechanics remain unchanged - Holon structure is just a cleaner way to organize the same logic

## Implementation Plan

### Phase 1: Archive & Prototype (Day 1-2)
- Move existing `specs/` → `specs.old/`
- Select ONE representative spec file for prototyping
- Copy to new `specs/` directory
- Manually update to new `tests:` format (add `id`, convert `test:` → `tests:` array)
- Add `#[Ticket('spec-id')]` attribute to corresponding test class

### Phase 2: New Runner Implementation (Day 3-5)
- Create `machines/middleware-test-runner-holon/` directory structure
- Implement `holon.yml` with ExtendedState feature
- Implement `holon-functions.php` with module-level functions
- Implement `buildTestCommand()` function to parse `tests:` array format (PHPUnit & Generic types)
- Create `run.php` CLI entry point

### Phase 3: Single-Spec Validation (Day 6-7)
- Run new runner against the single migrated spec
- Debug and fix issues
- Ensure test execution works correctly
- Verify CLI interface matches old runner behavior

### Phase 4: Bulk Migration (Day 8-10)
- Build migration script to convert all specs from `specs.old/` → `specs/`
- Generate spec IDs using feature name + acceptance criteria slug
- Add `#[Ticket]` attributes to all test classes
- Validate migrated specs against JSON Schema

### Phase 5: Integration (Day 11-12)
- Update composer scripts to use new runner
- Update CI/CD configuration
- Run full test suite to verify no regressions
- Update documentation

### Phase 6: Finalization (Day 13-14)
- Archive old runners to `machines.old/` (or delete if agreed)
- Remove temporary `specs.old/` directory (or keep as backup)
- Final validation
- Post-migration review

## Appendix A: JSON Schema File

Save as `schemas/spec-schema.json`:

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "$id": "https://noem.dev/schemas/spec-schema.json",
  "title": "Spec Standard Schema",
  "type": "object",
  "required": ["name", "group", "features"],
  "properties": {
    "name": {
      "type": "string",
      "description": "Name of the spec"
    },
    "group": {
      "type": "string",
      "description": "Group categorization",
      "enum": ["core", "features", "chain", "machines"]
    },
    "description": {
      "type": "string",
      "description": "Detailed description"
    },
    "features": {
      "type": "array",
      "items": { "$ref": "#/definitions/Feature" }
    }
  },
  "definitions": {
    "Feature": {
      "type": "object",
      "required": ["name", "specs"],
      "properties": {
        "name": { "type": "string" },
        "description": { "type": "string" },
        "specs": {
          "type": "array",
          "items": { "$ref": "#/definitions/Spec" }
        }
      }
    },
    "Spec": {
      "type": "object",
      "required": ["id", "acceptanceCriteria", "criticality", "scope", "intent", "tests"],
      "properties": {
        "id": {
          "type": "string",
          "pattern": "^[a-z0-9]+(-[a-z0-9]+)*:[a-z0-9]+(-[a-z0-9]+)*$",
          "minLength": 5,
          "maxLength": 70,
          "description": "Machine-friendly spec identifier (kebab-case with colon separator) must use {feature}:{spec} format"
        },
        "acceptanceCriteria": { "type": "string" },
        "criticality": {
          "type": "string",
          "enum": ["contract", "constraint", "detail"]
        },
        "scope": {
          "type": "string",
          "enum": ["unit", "integration", "e2e"],
          "description": "Test pyramid level - where this test sits in the testing hierarchy"
        },
        "intent": { "type": "string" },
        "tests": {
          "type": "array",
          "items": {
            "oneOf": [
              { "$ref": "#/definitions/GenericTest" },
              { "$ref": "#/definitions/PHPUnitTest" }
            ]
          },
          "minItems": 1,
          "maxItems": 1
        }
      }
    },
    "GenericTest": {
      "type": "object",
      "required": ["type", "command"],
      "properties": {
        "type": { "const": "generic" },
        "command": {
          "type": "string",
          "minLength": 1
        },
        "environment": {
          "type": "object",
          "additionalProperties": { "type": "string" }
        },
        "workingDirectory": { "type": "string" },
        "timeout": {
          "type": "number",
          "minimum": 1
        }
      },
      "additionalProperties": false
    },
    "PHPUnitTest": {
      "type": "object",
      "required": ["type"],
      "properties": {
        "type": { "const": "phpunit" },
        "target": { "type": "string" },
        "filter": { "type": "string" },
        "group": {
          "oneOf": [
            { "type": "string" },
            { "type": "array", "items": { "type": "string" }, "minItems": 1 }
          ]
        },
        "excludeGroup": {
          "oneOf": [
            { "type": "string" },
            { "type": "array", "items": { "type": "string" }, "minItems": 1 }
          ]
        },
        "testsuite": {
          "oneOf": [
            { "type": "string" },
            { "type": "array", "items": { "type": "string" }, "minItems": 1 }
          ]
        },
        "excludeTestsuite": {
          "oneOf": [
            { "type": "string" },
            { "type": "array", "items": { "type": "string" }, "minItems": 1 }
          ]
        },
        "configuration": { "type": "string" },
        "noConfiguration": { "type": "boolean" },
        "bootstrap": { "type": "string" },
        "phpIni": {
          "type": "object",
          "additionalProperties": { "type": "string" }
        },
        "stopOnDefect": { "type": "boolean" },
        "stopOnFailure": { "type": "boolean" },
        "stopOnError": { "type": "boolean" },
        "stopOnWarning": { "type": "boolean" },
        "failOnWarning": { "type": "boolean" },
        "failOnRisky": { "type": "boolean" },
        "colors": {
          "type": "string",
          "enum": ["auto", "always", "never"]
        },
        "testdox": { "type": "boolean" },
        "verbose": {
          "type": "number",
          "enum": [0, 1, 2, 3]
        },
        "noProgress": { "type": "boolean" },
        "coverageHtml": { "type": "string" },
        "coverageClover": { "type": "string" },
        "coverageXml": { "type": "string" },
        "coverageText": {
          "oneOf": [
            { "type": "boolean" },
            { "type": "string" }
          ]
        },
        "noCoverage": { "type": "boolean" },
        "environment": {
          "type": "object",
          "additionalProperties": { "type": "string" }
        },
        "processIsolation": { "type": "boolean" },
        "noExtensions": { "type": "boolean" },
        "timeout": {
          "type": "number",
          "minimum": 1
        },
        "workingDirectory": { "type": "string" }
      },
      "anyOf": [
        { "required": ["target"] },
        { "required": ["filter"] },
        { "required": ["group"] },
        { "required": ["testsuite"] }
      ],
      "additionalProperties": false
    }
  }
}
```

## Appendix B: Complete PHPUnit Option Mapping

| PHPUnit Option | Config Property | Type | Default | Notes |
|---|---|---|---|---|
| `<target>` | `target` | string | - | File or directory |
| `--filter` | `filter` | string | - | Regex pattern |
| `--group` | `group` | string\|string[] | - | Can specify multiple |
| `--exclude-group` | `excludeGroup` | string\|string[] | - | Can specify multiple |
| `--testsuite` | `testsuite` | string\|string[] | - | Suite name(s) |
| `--exclude-testsuite` | `excludeTestsuite` | string\|string[] | - | Suite name(s) |
| `-c` | `configuration` | string | - | Path to phpunit.xml |
| `--no-configuration` | `noConfiguration` | boolean | false | Ignore phpunit.xml |
| `--bootstrap` | `bootstrap` | string | - | Bootstrap file |
| `-d` | `phpIni` | Record<string,string> | - | PHP ini settings |
| `--stop-on-defect` | `stopOnDefect` | boolean | false | Stop on any issue |
| `--stop-on-failure` | `stopOnFailure` | boolean | false | Stop on failure |
| `--stop-on-error` | `stopOnError` | boolean | false | Stop on error |
| `--fail-on-warning` | `failOnWarning` | boolean | false | Fail on warning |
| `--fail-on-risky` | `failOnRisky` | boolean | false | Fail on risky |
| `--colors` | `colors` | 'auto'\|'always'\|'never' | 'auto' | Output colors |
| `--testdox` | `testdox` | boolean | false | TestDox format |
| `-v` | `verbose` | 0\|1\|2\|3 | 0 | Verbosity level |
| `--process-isolation` | `processIsolation` | boolean | false | Separate processes |
| `--no-extensions` | `noExtensions` | boolean | false | Disable extensions |
| `--coverage-html` | `coverageHtml` | string | - | HTML coverage dir |
| `--coverage-clover` | `coverageClover` | string | - | Clover XML file |
| `--coverage-xml` | `coverageXml` | string | - | XML coverage dir |
| `--coverage-text` | `coverageText` | boolean\|string | - | Text coverage file |
| `--no-coverage` | `noCoverage` | boolean | false | Disable coverage |

---

**This proposal mandates a complete migration of the spec standard. All existing specs and test infrastructure must be updated to the new format within the 5-week timeline.**
