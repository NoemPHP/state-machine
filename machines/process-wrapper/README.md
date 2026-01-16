# Process Wrapper

Wraps any Holon into a Unix-style process with file descriptors for inter-process communication.

## Usage

```bash
# Interactive mode (default) - reads from terminal stdin, echoes to stdout
php run.php machines/process-wrapper/holon.yml path/to/inner.yml

# Piped input
echo -e "hello\nworld\nexit" | php run.php machines/process-wrapper/holon.yml path/to/inner.yml

# Daemon mode - creates runtime directory with FIFOs/sockets for IPC
php run.php machines/process-wrapper/holon.yml -d path/to/inner.yml

# Or via environment variable
INNER_HOLON_PATH=path/to/inner.yml php run.php machines/process-wrapper/holon.yml
```

CLI argument takes precedence over environment variable.

## State Machine Architecture

```
detecting_mode
      │
      ├─── daemon_mode=true ──► setting_up_daemon ───┐
      │                                              │
      └─── daemon_mode=false ─► setting_up_interactive ─► setup_io
                                                          │
                                                          ▼
                                                   loading_holon
                                                          │
                                        ┌─────────────────┴─────────────────┐
                                        │                                   │
                                        ▼                                   ▼
                                     running ◄─────────────────────────   error
                                        │                                   │
                                        ▼                                   │
                                     shutdown ◄─────────────────────────────┘
                                        │
                                        ▼
                                      done
```

| Mode | Input Source | Local Echo | Use Case |
|------|--------------|------------|----------|
| Interactive | Real STDIN | Yes (stdout) | Terminal usage, debugging |
| Daemon (`-d`) | FIFO | No | Background service, multiple clients |

Both modes create a runtime directory with broadcast sockets for external observers.

## Runtime Directory

Created at `$XDG_RUNTIME_DIR/holon-{pid}/` (or `/tmp/holon-{pid}/` if unset).

A symlink `holon-latest` always points to the most recently started holon:

```
/tmp/holon-12345/
├── pid              # Process ID
├── stdin.fifo       # Named pipe for input (daemon mode)
├── control.sock     # Bidirectional socket (send input, receive output)
├── stdout.sock      # Unix socket for stdout broadcast (read-only)
├── stderr.sock      # Unix socket for stderr broadcast (read-only)
├── interactions.sock # Unix socket for interaction requests
├── logs.sock        # Unix socket for structured logs
└── manifest.json    # Channel configuration
```

## Connecting as a Client

### Interactive Shell (Recommended)

Use the included `connect.sh` script for a bidirectional interactive shell:

```bash
# Auto-detect running holon (if only one)
./machines/process-wrapper/connect.sh

# Connect to specific PID
./machines/process-wrapper/connect.sh 12345
```

This connects to `control.sock` - type commands and see responses in the same terminal.

### Manual Connection

```bash
HOLON_DIR="/tmp/holon-12345"

# Bidirectional control (preferred) - type and see output
socat - UNIX-CONNECT:"$HOLON_DIR/control.sock"

# Or with netcat (OpenBSD variant)
nc -U "$HOLON_DIR/control.sock"

# Send input via FIFO (daemon mode only, write-only)
echo '{"type":"start","payload":{}}' > "$HOLON_DIR/stdin.fifo"

# Receive stdout only (read-only)
socat - UNIX-CONNECT:"$HOLON_DIR/stdout.sock"

# Receive logs only (read-only)
socat - UNIX-CONNECT:"$HOLON_DIR/logs.sock"
```

### PHP

```php
$manifest = json_decode(file_get_contents('/tmp/holon-12345/manifest.json'), true);

// Send input via FIFO (daemon mode)
$stdin = fopen($manifest['channels']['stdin']['path'], 'w');
fwrite($stdin, json_encode(['type' => 'start']) . "\n");
fclose($stdin);

// Receive stdout via socket
$stdout = stream_socket_client('unix://' . $manifest['channels']['stdout']['path']);
while ($line = fgets($stdout)) {
    echo $line;
}
```

## Input Parsing

Input is buffered until the field separator (newline by default). Each segment is:

1. Checked for JSON structure (`{` or `[` at start)
2. If valid JSON: parsed and hydrated to `Message` (or `StandardMessage` for unknown types)
3. Otherwise: passed through as `(object)['raw_input' => $segment]`

## Signal Handling

- **SIGINT (Ctrl+C)**: First press shows warning, second press within 2 seconds force quits
- **SIGTERM**: Graceful shutdown

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `XDG_RUNTIME_DIR` | `/tmp` | Base directory for runtime files |
| `IFS` | `\n` | Field separator for input parsing |
| `INNER_HOLON_PATH` | (none) | Path to inner holon (alternative to CLI arg) |

## Files

| File | Purpose |
|------|---------|
| `holon.yml` | Main state machine definition |
| `bootstrap.php` | PSR-4 autoloader for `ProcessWrapper\` namespace |
| `connect.sh` | Shell script to connect to running holon |
| `src/RuntimeDirectory.php` | Manages runtime dir, FIFOs, sockets |
| `src/BroadcastChannel.php` | Socket-based multi-client broadcast (read-only) |
| `src/ControlChannel.php` | Bidirectional socket for interactive control |
| `src/StreamParser.php` | IFS-delimited parsing with JSON detection |
