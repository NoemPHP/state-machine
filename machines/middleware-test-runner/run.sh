#!/bin/bash

# Unified Test Runner Script
# Usage: ./run.sh [options]

set -e

# Parse arguments
EXTRA_ARGS=""
for arg in "$@"; do
    case $arg in
        --help)
            echo "Unified Test Runner"
            echo ""
            echo "Usage: ./run.sh [options]"
            echo ""
            echo "Options:"
            echo "  --spec=<path>         Path to spec YAML file (default: specs/chain/middleware.yaml)"
            echo "  --group=<n>           Run only specific feature group"
            echo "  --stop-on-failure     Stop execution on first failure"
            echo "  --quiet, -q           Suppress output except for errors (quiet mode)"
            echo "  --verbose, -v         Show detailed output including test outputs"
            echo "  --help                Show this help message"
            echo ""
            echo "Examples:"
            echo "  ./run.sh                                    # Run all tests"
            echo "  ./run.sh --group=chain                      # Run only chain tests"
            echo "  ./run.sh --stop-on-failure                  # Stop on first failure"
            echo "  ./run.sh --quiet                            # Show only errors"
            echo "  ./run.sh --verbose                          # Show detailed output"
            echo "  ./run.sh --spec=specs/myspec.yaml           # Use custom spec file"
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
