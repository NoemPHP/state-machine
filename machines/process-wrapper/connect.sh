#!/bin/bash
#
# Connect to a running holon's control socket for bidirectional communication.
#
# Usage:
#   ./connect.sh [PID]        # Connect to holon with given PID
#   ./connect.sh              # Auto-detect running holon (if only one)
#
# Supports: socat (preferred), nc/netcat (OpenBSD variant with -U flag)
#

set -e

# Find runtime directory
find_holon_dir() {
    local pid="$1"
    local base_dir="${XDG_RUNTIME_DIR:-/tmp}"

    if [ -n "$pid" ]; then
        # Specific PID given
        local dir="$base_dir/holon-$pid"
        if [ -d "$dir" ]; then
            echo "$dir"
            return 0
        fi
        echo "Error: No holon runtime directory found for PID $pid" >&2
        return 1
    fi

    # Try holon-latest symlink first
    local latest="$base_dir/holon-latest"
    if [ -L "$latest" ] && [ -d "$latest" ]; then
        echo "$latest"
        return 0
    fi

    # Fallback: find all holon directories
    local dirs=("$base_dir"/holon-[0-9]*)
    local valid_dirs=()

    for d in "${dirs[@]}"; do
        [ -d "$d" ] && valid_dirs+=("$d")
    done

    case ${#valid_dirs[@]} in
        0)
            echo "Error: No running holons found in $base_dir" >&2
            return 1
            ;;
        1)
            echo "${valid_dirs[0]}"
            return 0
            ;;
        *)
            echo "Error: Multiple holons found. Please specify PID:" >&2
            for d in "${valid_dirs[@]}"; do
                local p=$(basename "$d" | sed 's/holon-//')
                echo "  ./connect.sh $p" >&2
            done
            return 1
            ;;
    esac
}

# Detect available connection tool
detect_tool() {
    # Prefer socat - most reliable for Unix sockets
    if command -v socat >/dev/null 2>&1; then
        echo "socat"
        return 0
    fi

    # Try nc/netcat with -U flag (OpenBSD variant)
    if command -v nc >/dev/null 2>&1; then
        if nc -h 2>&1 | grep -q '\-U'; then
            echo "nc"
            return 0
        fi
    fi

    # Try netcat
    if command -v netcat >/dev/null 2>&1; then
        if netcat -h 2>&1 | grep -q '\-U'; then
            echo "netcat"
            return 0
        fi
    fi

    echo "Error: No compatible tool found. Install socat or OpenBSD netcat:" >&2
    echo "  apt install socat      # Debian/Ubuntu" >&2
    echo "  brew install socat     # macOS" >&2
    echo "  apk add socat          # Alpine" >&2
    return 1
}

# Connect to socket
connect() {
    local socket="$1"
    local tool="$2"

    echo "Connecting to $socket using $tool..." >&2
    echo "Type commands and press Enter. Ctrl+C to disconnect." >&2
    echo "---" >&2

    case "$tool" in
        socat)
            socat - "UNIX-CONNECT:$socket"
            ;;
        nc|netcat)
            "$tool" -U "$socket"
            ;;
    esac
}

# Main
main() {
    local pid="$1"

    local holon_dir
    holon_dir=$(find_holon_dir "$pid") || exit 1

    local control_socket="$holon_dir/control.sock"
    if [ ! -S "$control_socket" ]; then
        echo "Error: Control socket not found at $control_socket" >&2
        echo "The holon may not support bidirectional control." >&2
        exit 1
    fi

    local tool
    tool=$(detect_tool) || exit 1

    connect "$control_socket" "$tool"
}

main "$@"
