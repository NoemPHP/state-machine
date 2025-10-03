# Middleware Test Runner

A state machine-based test runner for executing acceptance criteria tests defined in YAML specifications.

## Features

- **YAML-based test specifications**: Define test suites and acceptance criteria in structured YAML files
- **Feature grouping**: Organize tests into logical feature groups
- **Flexible output modes**: Normal, quiet (errors only), or verbose (with test output)
- **Stop on failure**: Option to halt execution on the first test failure
- **Comprehensive error reporting**: Detailed failure information for debugging

## Usage

### Basic Usage
```bash
# Run all tests in the default spec file
ddev exec machines/middleware-test-runner/run.sh

# Or directly via PHP
ddev exec php machines/middleware-test-runner/machine.php
```

### Command Line Options

```bash
# Show only errors (quiet mode)
ddev exec machines/middleware-test-runner/run.sh --quiet
ddev exec machines/middleware-test-runner/run.sh -q

# Show detailed output including test outputs (verbose mode)
ddev exec machines/middleware-test-runner/run.sh --verbose
ddev exec machines/middleware-test-runner/run.sh -v

# Stop on first failure
ddev exec machines/middleware-test-runner/run.sh --stop-on-failure

# Run tests from a specific spec file
ddev exec machines/middleware-test-runner/run.sh --spec=specs/myspec.yaml

# Run only a specific feature group
ddev exec machines/middleware-test-runner/run.sh --group=chain

# Combine options
ddev exec machines/middleware-test-runner/run.sh --quiet --stop-on-failure
```

### Help

```bash
ddev exec machines/middleware-test-runner/run.sh --help
```

## Output Modes

### Normal Mode (default)
- Shows test suite header and configuration
- Displays each feature group with description
- Shows each test with result (✅ PASSED / ❌ FAILED)
- Provides summary at the end

### Quiet Mode (`--quiet` or `-q`)
- Suppresses all output except errors
- Shows only failed tests with minimal information
- Ideal for CI/CD pipelines or quick checks

### Verbose Mode (`--verbose` or `-v`)
- Shows everything from normal mode
- Additionally displays output from successful tests
- Displays full error output for failed tests
- Useful for debugging test issues

## Spec File Format

Test specifications are defined in YAML format:

```yaml
name: middleware
group: chain
description: A comprehensive middleware system

features:
  - name: chain
    description: Core chain functionality
    specs:
      - acceptanceCriteria: A portable chain object can be created
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Chain/CreationTest.php
        
      - acceptanceCriteria: Chain supports linking middleware
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Chain/LinkingTest.php
        
  - name: performance
    description: Performance optimizations
    specs:
      - acceptanceCriteria: Chain reuses compiled middleware
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Performance/ReuseTest.php
```

## Architecture

The test runner is implemented as a state machine with the following states:

1. **preparing**: Initializes test environment and loads spec file
2. **running_tests**: Executes all tests sequentially
3. **stopping**: Handles early termination (stop-on-failure)
4. **final_summary**: Generates success or failure summary
5. **complete**: Final state

## Exit Codes

- `0`: All tests passed
- `1`: One or more tests failed

## Development

The test runner consists of:
- `machine.php`: Main PHP script containing test execution logic
- `machine.yml`: State machine definition
- `run.sh`: Bash wrapper script for easier execution
- `README.md`: This documentation

## Troubleshooting

### Tests Not Running
- Ensure DDEV is running: `ddev start`
- Check that test files exist at the specified paths
- Verify PHPUnit is installed: `ddev exec composer install`

### Quiet Mode Still Shows Output
- Failed tests are always shown, even in quiet mode
- Use `--verbose` to see successful test output as well

### Script Hangs
- Use `--stop-on-failure` to exit early if tests are hanging
- Check for infinite loops in test code
- Verify test commands are correct
