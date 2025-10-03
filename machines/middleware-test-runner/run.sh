#!/bin/bash

# Unified Test Runner Script
# Usage: ./run.sh --spec=<path> [options]

set -e

# Parse arguments
EXTRA_ARGS=""
for arg in "$@"; do
    case $arg in
        --help)
            echo "Unified Test Runner"
            echo ""
            echo "Usage: ./run.sh --spec=<path> [options]"
            echo ""
            echo "Required:"
            echo "  --spec=<path>         Path to spec YAML file (required)"
            echo ""
            echo "Options:"
            echo "  --group=<name>        Run only specific feature group"
            echo "  --stop-on-failure     Stop execution on first failure"
            echo "  --quiet, -q           Suppress output except for errors (quiet mode)"
            echo "  --verbose, -v         Show detailed output including test outputs"
            echo "  --help                Show this help message"
            echo ""
            echo "Examples:"
            echo "  ./run.sh --spec=specs/core/region.yaml"
            echo "  ./run.sh --spec=specs/chain/middleware.yaml --quiet"
            echo "  ./run.sh --spec=specs/core/region.yaml --group=state-management"
            echo "  ./run.sh --spec=specs/core/region.yaml --stop-on-failure"
            echo "  ./run.sh --spec=specs/core/region.yaml --verbose"
            exit 0
            ;;
        *)
            EXTRA_ARGS="$EXTRA_ARGS $arg"
            ;;
    esac
done

# Get the directory of this script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

echo "🚀 Starting Test Runner"
if [ -n "$EXTRA_ARGS" ]; then
    echo "   Options:$EXTRA_ARGS"
fi
echo ""

# Run the test runner
php "$SCRIPT_DIR/machine.php" $EXTRA_ARGS

# Capture exit code
EXIT_CODE=$?

# Show result summary
echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "✅ Test runner completed successfully - all tests passed"
else
    echo "❌ Test runner completed with failures (exit code: $EXIT_CODE)"
    echo ""
    echo "⚠️  IMPORTANT: Review the detailed failure information above."
    echo "   The output includes comprehensive error details to guide fixes."
fi

exit $EXIT_CODE
