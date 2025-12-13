# Parallel Middleware Test Runner

A state machine-based test runner for executing acceptance criteria tests in parallel - one process per feature.

## Features

- **Parallel execution**: Runs each feature's tests in a separate process for maximum performance
- **YAML-based test specifications**: Define test suites and acceptance criteria in structured YAML files
- **Configurable parallelism**: Control the maximum number of concurrent threads
- **Feature grouping**: Organize tests into logical feature groups
- **Flexible output modes**: Normal, quiet (errors only), or verbose (with test output)
- **Stop on failure**: Option to halt execution on the first test failure
- **Comprehensive error reporting**: Detailed failure information for debugging

## Usage

### Basic Usage
```bash
# Run tests in parallel (--spec parameter is required)
ddev exec machines/middleware-test-runner-parallel/run.sh --spec=specs/core/region.yaml

# Or directly via PHP
ddev exec php machines/middleware-test-runner-parallel/machine.php --spec=specs/core/region.yaml
```

### Command Line Options

```bash
# Control parallelism (default: 4 threads)
ddev exec machines/middleware-test-runner-parallel/run.sh --spec=specs/chain/middleware.yaml --max-threads=8

# Show only errors (quiet mode)
ddev exec machines/middleware-test-runner-parallel/run.sh --spec=specs/chain/middleware.yaml --quiet
ddev exec machines/middleware-test-runner-parallel/run.sh --spec=specs/chain/middleware.yaml -q

# Show detailed output including test outputs (verbose mode)
ddev exec machines/middleware-test-runner-parallel/run.sh --spec=specs/core/region.yaml --verbose
ddev exec machines/middleware-test-runner-parallel/run.sh --spec=specs/core/region.yaml -v

# Stop on first failure
ddev exec machines/middleware-test-runner-parallel/run.sh --spec=specs/core/region.yaml --stop-on-failure

# Run only a specific feature group
ddev exec machines/middleware-test-runner-parallel/run.sh --spec=specs/chain/middleware.yaml --group=chain

# Combine options
ddev exec machines/middleware-test-runner-parallel/run.sh --spec=specs/core/region.yaml --max-threads=6 --quiet --stop-on-failure
```

### Help

```bash
ddev exec machines/middleware-test-runner-parallel/run.sh --help
```

## How It Works

The parallel test runner executes each feature in a separate PHP process:

1. **Feature Detection**: Reads the spec file and identifies all features
2. **Batching**: Groups features into batches based on `--max-threads`
3. **Parallel Execution**: Launches one process per feature in each batch
4. **Result Collection**: Gathers results from all processes via JSON output
5. **Reporting**: Merges and displays results with comprehensive summary

### Parallelism Strategy

- **One process per feature**: Each feature runs in its own PHP process
- **Batch processing**: Features are processed in batches to respect thread limits
- **Within-feature sequential**: Tests within a feature still run sequentially
- **Automatic fallback**: Falls back to sequential execution for single-feature specs

## Performance Benefits

For specs with multiple features, parallel execution can significantly reduce total runtime:

```
Sequential (4 features @ 10s each):  40s total
Parallel   (4 features @ 10s each):  10s total (4x speedup)
```

The speedup is proportional to:
- Number of features in the spec
- `--max-threads` setting
- Available CPU cores

## Output Modes

### Normal Mode (default)
- Shows test suite header and configuration
- Displays parallel execution status for each feature
- Shows each feature completion with pass/fail counts
- Provides comprehensive summary at the end

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

  - name: middleware
    description: Middleware execution
    specs:
      - acceptanceCriteria: Middleware can transform requests
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Middleware/TransformTest.php
```

## Architecture

The test runner is implemented as a state machine with the following states:

1. **preparing**: Initializes test environment and loads spec file
2. **running_tests**: Executes all tests in parallel (one process per feature)
3. **stopping**: Handles early termination (stop-on-failure)
4. **final_summary**: Generates success or failure summary
5. **complete**: Final state

## Exit Codes

- `0`: All tests passed
- `1`: One or more tests failed

## Comparison with Sequential Runner

| Feature | Sequential | Parallel |
|---------|-----------|----------|
| Execution | One test at a time | One process per feature |
| Speed | Slower for multiple features | Faster for multiple features |
| Resource usage | Low | Higher (multiple processes) |
| Best for | Single feature specs | Multi-feature specs |

## When to Use

**Use Parallel Runner when:**
- Your spec has multiple features
- Features are independent
- You want faster test execution
- You have sufficient CPU cores

**Use Sequential Runner when:**
- Your spec has a single feature
- Tests have resource contention issues
- You need minimal resource usage
- Debugging test failures

## Development

The test runner consists of:
- `machine.php`: Main PHP script containing parallel execution logic
- `machine.yml`: State machine definition
- `run.sh`: Bash wrapper script for easier execution
- `README.md`: This documentation

## Troubleshooting

### Tests Not Running
- Ensure DDEV is running: `ddev start`
- Check that test files exist at the specified paths
- Verify PHPUnit is installed: `ddev exec composer install`

### Parallel Execution Issues
- Reduce `--max-threads` if experiencing resource constraints
- Check for process limits: `ulimit -u`
- Use sequential runner for debugging

### Performance Not Improving
- Ensure your spec has multiple features (parallelism is per-feature)
- Check CPU utilization during test runs
- Consider I/O bottlenecks (database, filesystem)

### Process Errors
- Check temp directory permissions: `/tmp`
- Verify `proc_open` is enabled in PHP
- Review error output for specific process failures

## Technical Details

### Process Management
- Uses `proc_open()` for process creation and management
- Temporary PHP scripts for isolated feature execution
- JSON-based result communication between processes
- Automatic cleanup of temporary files

### Resource Cleanup
- Temporary files are deleted after each feature completes
- Process handles are properly closed
- Stream buffers are flushed before reading results

### Error Handling
- Failed processes generate failure results for all specs
- JSON parsing errors are caught and reported
- Process execution errors include stderr output
