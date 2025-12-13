#!/bin/bash

# Parallel Middleware Test Runner
# Executes acceptance criteria tests in parallel - one thread per feature

php "$(dirname "$0")/machine.php" "$@"
