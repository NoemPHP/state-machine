# Process Wrapper Proposal

**Status**: Draft
**Created**: 2026-01-13
**Author**: Discussion between user and Claude

## Problem Statement

The current `cli-agent` architecture (see `machines/cli-agent/`) wraps a Holon and directly transforms it into a CLI application. This works but has several limitations:

1. **Tight I/O coupling**: `InteractionAdapter` uses `fgets(STDIN)` and `echo` directly
2. **PHP-only consumers**: Other languages/tools cannot easily integrate
3. **No separation of transport and logic**: Output, logging, and interactions are intermingled
4. **Difficult to test**: Requires mocking STDIN/STDOUT
5. **Single adapter pattern**: Web, GUI, or other interfaces require reimplementation

### Current Architecture

```
┌─────────────────────────────────────────────────────┐
│                     CLI Agent                       │
│  ┌───────────────────────────────────────────────┐  │
│  │  InteractionAdapter                           │  │
│  │  - fgets(STDIN) for input                     │  │
│  │  - echo for output                            │  │
│  │  - Directly handles all interaction types     │  │
│  └───────────────────────────────────────────────┘  │
│                        │                            │
│                        ▼                            │
│  ┌───────────────────────────────────────────────┐  │
│  │  Summoned Holon (machine-agent)               │  │
│  │  - InteractionRequest emission                │  │
│  │  - State machine logic                        │  │
│  └───────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────┘
```

## Proposed Solution

Introduce an intermediate **Process** layer that wraps any Holon into a standard Unix-style process with well-defined file descriptors and a JSON API.

### Proposed Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Process Wrapper                              │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  File Descriptors                                             │  │
│  │  ├─ FD 0 (stdin)  - JSON commands/responses IN               │  │
│  │  ├─ FD 1 (stdout) - JSON data/results OUT (default output)   │  │
│  │  ├─ FD 2 (stderr) - Errors and diagnostics                   │  │
│  │  ├─ FD 3 (logs)   - Structured logging (LoggingFeature)      │  │
│  │  ├─ FD 4 (interactions) - InteractionRequests OUT            │  │
│  │  └─ FD N (custom) - Bespoke channels as needed               │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                              │                                       │
│                              ▼                                       │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  Holon (any machine)                                          │  │
│  │  - Pure state machine logic                                   │  │
│  │  - No I/O assumptions                                         │  │
│  └───────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
         │            │            │            │
         ▼            ▼            ▼            ▼
    ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐
    │   CLI   │  │   Web   │  │  Bash   │  │  Any    │
    │ Adapter │  │ Adapter │  │ Script  │  │ Process │
    └─────────┘  └─────────┘  └─────────┘  └─────────┘
```

### File Descriptor Assignments

| FD | Name | Direction | Purpose |
|----|------|-----------|---------|
| 0 | stdin | IN | JSON commands, interaction responses |
| 1 | stdout | OUT | Primary data output (streaming supported) |
| 2 | stderr | OUT | Errors, stack traces, diagnostics |
| 3 | logs | OUT | Structured log entries (LoggingFeature) |
| 4 | interactions | OUT | InteractionRequests (for external handling) |
| 5+ | custom | IN/OUT | Application-specific channels |

### Data Format Philosophy

**Core principle**: The process wrapper is a **transparent pipe** by default. Structured message handling is an optional layer that *attempts* deserialization without requiring it.

This means:
- Raw text/binary flows through unmodified
- JSON is detected and parsed *opportunistically*
- Non-JSON data is never rejected
- Streaming is friction-free

### Stream Parser Sub-Region

**Parsing Strategy: IFS-Delimited Buffering with JSON Detection**

We use **field separator delimited buffering**, not character-by-character parsing:

1. Read chunks from stream, accumulate in buffer
2. Buffer until we encounter the field separator (from `$IFS` env var, default `\n`)
3. On delimiter: check if buffered content is valid JSON
4. If JSON: parse, hydrate to Message object
5. If not JSON: emit as raw data
6. Continue buffering remainder

```
Input:  "hello\n{\"type\":\"start\"}\nworld\n"

Chunks arrive unpredictably:
  Chunk 1: "hel"
  Chunk 2: "lo\n{\"typ"        ← delimiter hit! emit "hello" as raw
  Chunk 3: "e\":\"start\"}\nwor"  ← delimiter hit! emit JSON as Message
  Chunk 4: "ld\n"              ← delimiter hit! emit "world" as raw
```

**Key insight**: We always buffer. When a chunk arrives containing the delimiter, we split, process the complete segment, and keep the remainder in the buffer. Chunks never get "lost".

**IFS Environment Variable**: Unix's Input Field Separator can be overridden externally (`IFS='\0'`, `IFS=':'`). Our parser reads `$IFS` at startup, allowing custom segmentation without code changes.

#### Native Sub-Region Implementation

Instead of a separate PHP class, we implement the stream parser as a **native YAML-based sub-region**, dogfooding the framework:

```yaml
# stream-parser.holon.yml
# Sub-region that parses byte streams into Messages or RawData

machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\Async\AsyncFeature

states:
  - name: buffering
    action:
      # language=injectablephp
      - run: !php |
          return function(object $t): \Generator {
              $buffer = $this->get('buffer', '');
              $ifs = $this->get('field_separator', "\n");
              $chunk = $t->chunk ?? '';

              $buffer .= $chunk;

              // Process all complete segments in buffer
              while (($delimPos = strpos($buffer, $ifs)) !== false) {
                  $segment = substr($buffer, 0, $delimPos);
                  $buffer = substr($buffer, $delimPos + strlen($ifs));

                  // Attempt JSON parse
                  $trimmed = trim($segment);
                  $firstChar = $trimmed[0] ?? '';

                  if (($firstChar === '{' || $firstChar === '[')) {
                      $decoded = json_decode($trimmed, associative: true);
                      if (json_last_error() === JSON_ERROR_NONE) {
                          $this->dispatch((object)[
                              'type' => 'parsed_message',
                              'data' => $decoded,
                              'is_json' => true,
                          ]);
                          continue;
                      }
                  }

                  // Not JSON - emit as raw
                  if ($segment !== '') {
                      $this->dispatch((object)[
                          'type' => 'raw_data',
                          'data' => $segment,
                          'is_json' => false,
                      ]);
                  }
              }

              $this->set('buffer', $buffer);
              yield;
          };
        async:
          enabled: true

initial: buffering
final: buffering  # Continuous - never terminates
```

#### Initialization

```php
// Process wrapper initializes parser with IFS from environment
$parser = $this->summon('stream-parser.holon.yml');

$parser->trigger((object)[
    'type' => 'init',
    'field_separator' => getenv('IFS') ?: "\n",
]);

// Feed chunks as they arrive
$parser->trigger((object)['chunk' => $incomingData]);
```

#### Binary Mode (Character-Level Fallback)

For streams without delimiters (binary data), use brace-matching mode that tracks:
- Brace depth (`{`/`}`)
- Bracket depth (`[`/`]`)
- String context (ignore structural chars inside `"..."`)
- Escape sequences (`\"` doesn't end string)

```yaml
# Add to stream-parser.holon.yml for binary mode
- name: binary_mode
  action:
    # language=injectablephp
    - run: !php |
        return function(object $t): \Generator {
            $char = $t->char ?? '';
            $buffer = $this->get('buffer', '');
            $inString = $this->get('in_string', false);
            $escaped = $this->get('escaped', false);
            $depth = $this->get('brace_depth', 0);

            if ($escaped) {
                $buffer .= $char;
                $this->set('escaped', false);
            } elseif ($inString) {
                $buffer .= $char;
                if ($char === '\\') $this->set('escaped', true);
                elseif ($char === '"') $this->set('in_string', false);
            } else {
                $buffer .= $char;
                match ($char) {
                    '{' => $this->set('brace_depth', $depth + 1),
                    '}' => $this->set('brace_depth', $depth - 1),
                    '"' => $this->set('in_string', true),
                    default => null,
                };

                // Complete JSON object?
                if ($char === '}' && $this->get('brace_depth') === 0 && $buffer !== '') {
                    $decoded = json_decode($buffer, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $this->dispatch((object)['type' => 'parsed_message', 'data' => $decoded]);
                    }
                    $buffer = '';
                }
            }

            $this->set('buffer', $buffer);
            yield;
        };
      async:
        enabled: true
```

### Output Handling (equally flexible)

```php
public function emit(mixed $data, int $fd = 1): void
{
    $output = match (true) {
        // Message objects serialize via their own method
        $data instanceof Message => $data->serialize($this->serializer),

        // JsonSerializable uses JSON
        is_object($data) && $data instanceof \JsonSerializable
            => json_encode($data),

        // Strings pass through raw
        is_string($data) => $data,

        // Arrays/objects default to JSON attempt
        default => json_encode($data) ?: serialize($data),
    };

    fwrite($this->fds[$fd], $output);

    // Newline delimiter only in line-delimited mode
    if ($this->lineDelimited) {
        fwrite($this->fds[$fd], "\n");
    }
}
```

### Message Examples (when structured data is used)

These are **conventions**, not requirements. Raw data is always valid.

#### Input (stdin) - Structured Commands

```json
{"type": "start", "payload": {"description": "Create a todo list"}}
{"type": "interaction_response", "correlation_id": "uuid-123", "data": {"input": "My answer"}}
{"type": "trigger", "event": "user_action", "payload": {...}}
{"type": "shutdown"}
```

#### Input (stdin) - Raw Data (equally valid)

```
Hello, process!
Any text works here
Even incomplete JSON like {"broken
Binary data: \x00\x01\x02
```

#### Output (stdout)

```json
{"type": "data", "payload": {...}}
{"type": "state_changed", "from": "idle", "to": "processing"}
{"type": "complete", "result": {...}}
{"type": "progress", "percent": 45, "message": "Generating..."}
```

Or raw streaming output:
```
Generating response...
Here is some text that streams character by character
without any JSON framing at all.
```

#### Interactions (FD 4)

```json
{"type": "prompt", "correlation_id": "uuid-123", "question": "What name?"}
{"type": "confirm", "correlation_id": "uuid-456", "question": "Proceed?", "default": true}
{"type": "select", "correlation_id": "uuid-789", "question": "Choose:", "options": [...]}
```

#### Logs (FD 3)

```json
{"level": "info", "message": "Processing started", "timestamp": 1705142400}
{"level": "debug", "message": "State transition", "data": {"from": "A", "to": "B"}}
```

Or plain text logs:
```
[INFO] Processing started
[DEBUG] State transition: A → B
```

### Message Hydration (opportunistic casting)

When JSON is successfully parsed, the wrapper *attempts* to hydrate it into a typed Message object. This is best-effort - failure falls back to raw associative array.

```php
class MessageHydrator
{
    private array $typeMap = [
        'start'                => StartCommand::class,
        'shutdown'             => ShutdownCommand::class,
        'trigger'              => TriggerCommand::class,
        'interaction_response' => InteractionResponse::class,
        // ... extensible
    ];

    public function hydrate(array $data): Message|array
    {
        // No type field? Return raw array
        if (!isset($data['type'])) {
            return $data;
        }

        // Unknown type? Return raw array
        $class = $this->typeMap[$data['type']] ?? null;
        if ($class === null) {
            return $data;
        }

        // Attempt construction - catch failures gracefully
        try {
            return $class::fromArray($data);
        } catch (\Throwable) {
            return $data;  // Malformed but valid JSON - return as-is
        }
    }

    public function register(string $type, string $class): void
    {
        $this->typeMap[$type] = $class;
    }
}
```

#### Holon Receives Typed or Raw

```php
// Inside the Holon's input handler
$input = $this->processWrapper->read();

match (true) {
    $input instanceof StartCommand => $this->handleStart($input),
    $input instanceof InteractionResponse => $this->deliverResponse($input),
    $input instanceof Message => $this->dispatch($input),  // Generic typed message
    is_array($input) => $this->handleRawJson($input),      // Valid JSON, unknown type
    is_string($input) => $this->handleRawText($input),     // Plain text/binary
};
```

#### Custom Message Types

Adapters or features can register custom message types:

```php
// A hypothetical AIFeature registers its message types
$hydrator->register('ai_response', AiResponseMessage::class);
$hydrator->register('tool_call', ToolCallMessage::class);

// Now incoming JSON like {"type": "ai_response", ...}
// automatically becomes an AiResponseMessage instance
```

### Framing Strategies

For environments needing explicit message boundaries (binary, no newlines):

| Strategy | Delimiter | Pros | Cons |
|----------|-----------|------|------|
| **Newline** | `\n` | Simple, human-readable, `jq` compatible | Can't embed newlines in data |
| **Length-prefix** | `4-byte length + payload` | Binary-safe, efficient | Not human-readable |
| **Null-byte** | `\0` | Simple, works with most text | Rare in text but exists |
| **Custom marker** | `\x1E` (record separator) | ASCII standard for this purpose | Uncommon |

```php
// Length-prefixed framing (binary mode)
public function writeFramed(string $data, int $fd): void
{
    $length = pack('N', strlen($data));  // 4-byte big-endian
    fwrite($this->fds[$fd], $length . $data);
}

public function readFramed(int $fd): ?string
{
    $lengthBytes = fread($this->fds[$fd], 4);
    if ($lengthBytes === false || strlen($lengthBytes) < 4) {
        return null;
    }
    $length = unpack('N', $lengthBytes)[1];
    return fread($this->fds[$fd], $length);
}
```

## External Client Connectivity

**Problem**: How do external processes connect to our FDs? How can multiple clients receive the same stdout?

### Discovery: Runtime Directory

The process wrapper creates a runtime directory advertising its FDs:

```
/tmp/holon-{pid}/
├── pid              # Process ID
├── stdin.fifo       # Named pipe for input
├── stdout.fifo      # Named pipe for output (or socket for broadcast)
├── stderr.fifo      # Named pipe for errors
├── interactions.fifo # Named pipe for interaction requests
├── logs.fifo        # Named pipe for structured logs
└── manifest.json    # FD configuration and capabilities
```

Or in working directory (configurable):

```
.holon/
├── pid
├── stdin.sock       # Unix domain socket
├── stdout.sock      # Unix domain socket (supports multiple clients)
├── manifest.json
└── ...
```

#### Manifest File

```json
{
  "pid": 12345,
  "started_at": "2026-01-13T10:30:00Z",
  "field_separator": "\n",
  "channels": {
    "stdin": {"type": "fifo", "path": "/tmp/holon-12345/stdin.fifo", "direction": "in"},
    "stdout": {"type": "socket", "path": "/tmp/holon-12345/stdout.sock", "direction": "out", "broadcast": true},
    "stderr": {"type": "fifo", "path": "/tmp/holon-12345/stderr.fifo", "direction": "out"},
    "interactions": {"type": "socket", "path": "/tmp/holon-12345/interactions.sock", "direction": "out"},
    "logs": {"type": "fifo", "path": "/tmp/holon-12345/logs.fifo", "direction": "out"}
  },
  "capabilities": ["json_messages", "raw_passthrough", "interactions", "presentations"]
}
```

### Multiple Clients: The Broadcast Problem

**Named pipes (FIFOs)**: Multiple readers each get partial data (round-robin), not broadcast. This works for stdin (multiple senders, single consumer) but not for stdout (single sender, multiple consumers wanting same data).

**Solution: Unix Domain Sockets for Broadcast Channels**

```
┌─────────────────────────────────────────────────────────────────┐
│  Process Wrapper                                                │
│                                                                 │
│  ┌─────────────────────┐                                       │
│  │  Holon              │                                       │
│  │  (state machine)    │                                       │
│  └──────────┬──────────┘                                       │
│             │ emit()                                            │
│             ▼                                                   │
│  ┌─────────────────────┐      ┌─────────────────────────────┐ │
│  │  Broadcast Manager  │─────▶│  stdout.sock                 │ │
│  │                     │      │  (Unix domain socket)        │ │
│  │  Maintains list of  │      │                              │ │
│  │  connected clients  │      │  Client A ◄────────────────┐ │ │
│  │                     │      │  Client B ◄────────────────┤ │ │
│  │  Sends same data    │      │  Client C ◄────────────────┘ │ │
│  │  to ALL clients     │      └─────────────────────────────┘ │
│  └─────────────────────┘                                       │
└─────────────────────────────────────────────────────────────────┘
```

#### Broadcast Implementation

```php
class BroadcastChannel
{
    private string $socketPath;
    private $serverSocket;
    private array $clients = [];

    public function __construct(string $socketPath)
    {
        $this->socketPath = $socketPath;
        $this->serverSocket = stream_socket_server(
            "unix://{$socketPath}",
            $errno, $errstr
        );
        stream_set_blocking($this->serverSocket, false);
    }

    public function tick(): void
    {
        // Accept new connections
        while ($client = @stream_socket_accept($this->serverSocket, 0)) {
            stream_set_blocking($client, false);
            $this->clients[] = $client;
        }

        // Remove disconnected clients
        $this->clients = array_filter($this->clients, fn($c) => is_resource($c));
    }

    public function broadcast(string $data): void
    {
        foreach ($this->clients as $client) {
            @fwrite($client, $data);
        }
    }
}
```

#### Client Connection (Bash Example)

```bash
#!/bin/bash
# Connect to a running holon process

HOLON_DIR="/tmp/holon-12345"

# Read manifest
MANIFEST=$(cat "$HOLON_DIR/manifest.json")

# Connect to stdout socket (receives broadcast)
nc -U "$HOLON_DIR/stdout.sock" &
STDOUT_PID=$!

# Connect to interactions socket
nc -U "$HOLON_DIR/interactions.sock" &
INTERACT_PID=$!

# Send commands via stdin FIFO
echo '{"type":"start","payload":{}}' > "$HOLON_DIR/stdin.fifo"

# Cleanup on exit
trap "kill $STDOUT_PID $INTERACT_PID 2>/dev/null" EXIT
wait
```

#### Client Connection (Node.js Example)

```javascript
const net = require('net');
const fs = require('fs');

const manifest = JSON.parse(fs.readFileSync('/tmp/holon-12345/manifest.json'));

// Connect to broadcast stdout
const stdout = net.createConnection(manifest.channels.stdout.path);
stdout.on('data', (data) => {
    console.log('Output:', data.toString());
});

// Connect to interactions
const interactions = net.createConnection(manifest.channels.interactions.path);
interactions.on('data', (data) => {
    const request = JSON.parse(data.toString());
    // Handle interaction request...
    // Send response via stdin
});

// Send input via stdin FIFO
const stdinFifo = fs.createWriteStream(manifest.channels.stdin.path);
stdinFifo.write(JSON.stringify({type: 'start', payload: {}}));
```

### Alternative: Multiplexed Single Socket

For simpler deployment, all channels can be multiplexed over a single socket with type prefixes:

```
┌─────────────────────────────────────────────────────┐
│  Single Socket: /tmp/holon-12345/holon.sock         │
│                                                     │
│  Messages prefixed with channel:                    │
│  @stdin:{"type":"start"}                           │
│  @stdout:{"type":"data","payload":{}}              │
│  @interaction:{"type":"prompt","question":"?"}     │
│  @log:{"level":"info","message":"Started"}         │
└─────────────────────────────────────────────────────┘
```

Clients parse the prefix to route messages appropriately.

### Cleanup

Process wrapper cleans up on exit:

```php
public function __destruct()
{
    // Close all client connections
    foreach ($this->broadcastChannels as $channel) {
        $channel->close();
    }

    // Remove runtime directory
    $this->removeRuntimeDir();
}

// Also handle SIGTERM/SIGINT
pcntl_signal(SIGTERM, function() {
    $this->cleanup();
    exit(0);
});
```

## Benefits

### 1. Language Agnostic

Any tool that can read/write JSON over pipes can integrate:

```bash
# Bash script as adapter
echo '{"type":"start","payload":{"description":"test"}}' | php process-wrapper.php 3>/dev/null

# Python adapter
import subprocess
proc = subprocess.Popen(['php', 'process-wrapper.php'], stdin=PIPE, stdout=PIPE)
proc.stdin.write(json.dumps({"type": "start", ...}))
```

### 2. Unix Philosophy

Composable processes using standard tools:

```bash
# Filter only state changes
php process-wrapper.php | jq 'select(.type == "state_changed")'

# Log to file, output to terminal
php process-wrapper.php 3>logs.jsonl 2>errors.log

# Chain processes
php process-a.php | php process-b.php
```

### 3. Clean Separation of Concerns

- **Process Wrapper**: Routes I/O between FDs and Holon
- **Adapter**: Translates between process FDs and specific medium (CLI, HTTP, WebSocket)
- **Holon**: Pure state machine logic, no I/O assumptions

### 4. Streaming Support

Natural fit for streaming responses (LLM output, progress, etc.):

```bash
# Stream stdout line by line
php process-wrapper.php | while read -r line; do
    echo "$line" | jq -r '.payload.chunk // empty'
done
```

### 5. Easy Testing

```bash
# Feed test input, capture output
cat test-input.jsonl | php process-wrapper.php > output.jsonl 2>errors.log
diff output.jsonl expected-output.jsonl
```

### 6. Web Server Integration

Any HTTP server can become a frontend:

```
HTTP Request
     │
     ▼
┌─────────────────────────────────────────────────┐
│  Web Server (nginx, Node, Go, etc.)             │
│  - Spawns process wrapper per request           │
│  - stdin: JSON request body                     │
│  - stdout: Stream to HTTP response              │
│  - FD 4: WebSocket for interactions             │
└─────────────────────────────────────────────────┘
     │           │            │
     ▼           ▼            ▼
  stdin       stdout     WebSocket
  (JSON)     (stream)   (interactions)
```

## Potential Issues & Considerations

### 1. Serialization Overhead

**Issue**: Everything must serialize to JSON.

**Mitigation**:
- JSON is already the lingua franca for most APIs
- For binary data, use base64 encoding or a separate FD
- Performance impact likely minimal compared to actual computation

### 2. Platform Compatibility

**Issue**: File descriptors beyond 0/1/2 may behave differently across platforms.

**Mitigation**:
- FDs 3+ are standard POSIX, supported on Linux/macOS
- Windows support via named pipes or different transport
- Could fall back to multiplexing on stdout with message framing

### 3. Synchronization & Deadlocks

**Issue**: Multiple FDs need careful handling to avoid blocking.

**Mitigation**:
- Use non-blocking I/O with select/poll
- Process wrapper manages event loop internally
- Line-delimited JSON (JSONL) for clear message boundaries

### 4. Error Handling

**Issue**: Where do errors go? stderr vs structured responses?

**Proposed convention**:
- **stderr (FD 2)**: Fatal errors, stack traces, diagnostics (for debugging)
- **stdout**: Structured error responses as JSON (for programmatic handling)
- Both can coexist - stderr for humans, stdout for machines

### 5. Debugging Complexity

**Issue**: Multiple pipes harder to debug than direct echo/fgets.

**Mitigation**:
- Debug mode that dumps all FDs to stderr
- Clear logging convention
- Tools to visualize pipe flow

### 6. Buffer Management

**Issue**: Large outputs may need buffering, partial reads.

**Mitigation**:
- Line-delimited JSON naturally handles this
- Each line is a complete message
- Process wrapper flushes after each message

### 7. Graceful Shutdown

**Issue**: Need to handle SIGTERM, EOF, and explicit shutdown.

**Proposed convention**:
- EOF on stdin: graceful shutdown
- SIGTERM: immediate but clean termination
- `{"type": "shutdown"}`: explicit request to complete and exit

## Implementation Components

### 1. ProcessWrapper Class

Core component that wraps any Holon:

```php
class ProcessWrapper {
    private Region $holon;
    private array $fileDescriptors = [];

    public function __construct(Region $holon) { ... }

    public function run(): int { ... }  // Returns exit code

    // Internal routing
    private function handleStdin(string $json): void { ... }
    private function emitStdout(array $data): void { ... }
    private function emitInteraction(InteractionRequest $req): void { ... }
    private function emitLog(string $level, string $message): void { ... }
}
```

### 2. ProcessFeature (optional)

Feature that automatically routes Holon events to correct FDs:

```php
class ProcessFeature implements Feature {
    public function apply(FeatureSupport $builder): FeatureSupport {
        // Subscribe to notification chain
        // Route InteractionRequests to FD 4
        // Route log events to FD 3
        // Route data events to stdout
    }
}
```

### 3. LoggingFeature (prerequisite)

Structured logging that integrates with ProcessWrapper:

```php
// Within Holon
$this->log('info', 'Processing started');
$this->log('debug', 'State data', ['key' => 'value']);

// Routed to FD 3 as JSON
```

### 4. CLI Adapter (simplified)

Thin adapter that just handles terminal I/O:

```bash
#!/bin/bash
# Simplified CLI adapter

# Start process with extra FDs
exec 3>&1  # logs to terminal
exec 4>&1  # interactions to terminal

php process-wrapper.php 3>&3 4>&4 | while read -r line; do
    type=$(echo "$line" | jq -r '.type')
    case "$type" in
        progress)
            echo "[$(echo "$line" | jq -r '.percent')%] $(echo "$line" | jq -r '.message')"
            ;;
        complete)
            echo "Done!"
            ;;
    esac
done
```

### 5. Web Adapter (example)

Express.js server consuming the process:

```javascript
const { spawn } = require('child_process');

app.post('/generate', (req, res) => {
    const proc = spawn('php', ['process-wrapper.php'], {
        stdio: ['pipe', 'pipe', 'pipe', 'pipe', 'pipe']
    });

    // Send request
    proc.stdin.write(JSON.stringify({
        type: 'start',
        payload: req.body
    }));
    proc.stdin.end();

    // Stream response
    res.setHeader('Content-Type', 'application/x-ndjson');
    proc.stdout.pipe(res);

    // Handle interactions via WebSocket (FD 4)
    proc.stdio[4].on('data', (data) => {
        websocket.send(data);
    });
});
```

## Migration Path

1. **Phase 1**: Implement `ProcessWrapper` class
2. **Phase 2**: Implement `LoggingFeature` for structured logs
3. **Phase 3**: Implement `ProcessFeature` for automatic routing
4. **Phase 4**: Refactor `cli-agent` to use ProcessWrapper
5. **Phase 5**: Create example web adapter

## Design Decisions

### 1. FD Configuration
**Decision**: Hardcoded mappings, prominently placed in YAML config for easy modification.

```yaml
# process-wrapper.holon.yml
process:
  channels:
    stdin: 0
    stdout: 1
    stderr: 2
    logs: 3
    interactions: 4
```

**Rationale**: Keeps implementation simple while allowing users to modify if needed by editing a single config section.

---

### 2. Raw Data Buffering
**Decision**: Configurable via parameter. Buffered (IFS-delimited) by default, with option to disable for passthrough mode.

```yaml
process:
  parsing:
    mode: buffered    # 'buffered' (default) or 'passthrough'
    delimiter: "\n"   # Reads from $IFS if not specified
```

- **buffered**: Parse on delimiter, attempt JSON cast, emit Message or raw
- **passthrough**: Forward data immediately without buffering or parsing

**Rationale**: Well-behaved clients sending newline-delimited JSON get proper parsing. Raw streaming use cases can bypass buffering entirely.

---

### 3. Multiplexing Fallback
**Decision**: No fallback. Multi-FD only.

**Rationale**: Keeps implementation focused. Windows support deferred to future work.

---

### 4. Process Lifecycle
**Decision**: Exit when inner Holon finishes. On SIGINT (Ctrl+C), prompt for confirmation once before terminating.

```php
pcntl_signal(SIGINT, function() {
    if ($this->confirmationPending) {
        // Second Ctrl+C - force exit
        exit(130);
    }

    fwrite(STDERR, "\nInterrupt received. Press Ctrl+C again to exit.\n");
    $this->confirmationPending = true;
});
```

**Rationale**: Clean exit on normal completion; graceful handling of user interrupts without accidental termination.

---

### 5. Platform Support
**Decision**: Linux-first. Windows support deferred.

**Rationale**: Ship faster, focus on primary use case. Windows can be added later without architectural changes.

---

### 6. Parser Implementation
**Decision**: Native sub-region (YAML Holon).

**Rationale**: Dogfoods the framework. Maintains consistency with rest of architecture. Slight overhead is acceptable for the architectural benefit.

---

### 7. Message Type Handling
**Decision**: Simple approach - check for `type` property, cast if present, otherwise use `StandardMessage`.

```php
// In hydrator
public function hydrate(array $data): Message
{
    if (isset($data['type']) && isset($this->typeMap[$data['type']])) {
        $class = $this->typeMap[$data['type']];
        return $class::fromArray($data);
    }

    // Fallback: wrap in StandardMessage
    return new StandardMessage($data);
}
```

**Note**: `StandardMessage` class needs to be introduced, specced, and tested.

**Rationale**: Avoids overengineering. Type-based casting when available, graceful fallback when not.

---

### 8. Runtime Directory Location
**Decision**: `$XDG_RUNTIME_DIR/holon-{pid}/` with fallback to `/tmp/holon-{pid}/` if unset.

```php
$runtimeDir = getenv('XDG_RUNTIME_DIR') ?: '/tmp';
$holonDir = "{$runtimeDir}/holon-{$pid}";
```

**Rationale**: Follows Linux standards. XDG_RUNTIME_DIR is typically `/run/user/{uid}` on modern systems, auto-cleaned on logout. Falls back gracefully.

---

### 9. Broadcast Mechanism
**Decision**: FIFOs for input, Unix domain sockets for output.

| Channel | Type | Rationale |
|---------|------|-----------|
| stdin | FIFO | Multiple writers → single reader (queue naturally) |
| stdout | Socket | Single writer → multiple readers (broadcast) |
| stderr | Socket | Single writer → multiple readers (broadcast) |
| interactions | Socket | Broadcast to all connected adapters |
| logs | Socket | Broadcast to all log consumers |

**Rationale**: Best of both worlds. Input naturally queues via FIFO. Output broadcasts to all interested clients via socket.

---

### 10. stdin Aggregation
**Decision**: Queue/FIFO ordering. Messages from multiple writers processed in arrival order.

**Rationale**: Fair, simple, no complex routing infrastructure needed. Multiple clients can write; process handles them sequentially.

---

## Implementation Strategy

### Spec-Driven Components

These components are pure PHP classes/features and will follow SDD:

| Component | Type | Description |
|-----------|------|-------------|
| `StandardMessage` | Class | Generic message wrapper for untyped JSON payloads |
| `LoggingFeature` | Feature | Structured logging with level/message/context |
| `MessageHydrator` | Class | Parse JSON, cast to typed Message or StandardMessage |
| `BroadcastChannel` | Class | Unix socket server for broadcasting to multiple clients |
| `StreamParser` | Class | IFS-delimited parsing with JSON detection |

### Non-Spec Components (Holon-based)

These are YAML-based machines that cannot be spec-tested (no Holon testing framework yet):

| Component | Type | Description |
|-----------|------|-------------|
| `process-wrapper.holon.yml` | Machine | Main wrapper that orchestrates everything |
| `stream-parser.holon.yml` | Machine | Parser as sub-region (uses StreamParser class internally) |

**Approach**: Build the spec-driven components first, then wire them together in the Holon machines.

---

## Open Items (Future Work)

- **Windows support**: Deferred, may use different transport layer
- **Holon testing framework**: Would enable SDD for machine definitions

## Related Work

- **AGENT_COMMUNICATION_STRATEGY.md**: Current approach to manager/sub-agent communication
- **InteractionFeature**: Existing interaction request/response system
- **MessageFeature**: Current event-based messaging

## Conclusion

The process wrapper approach aligns with Unix philosophy and provides a clean abstraction layer between Holons and their I/O adapters. The JSON-over-pipes protocol enables integration with any language or tool, making the state machine system more versatile and composable.

Key advantages:
- Any adapter can be written in any language
- Standard Unix tools (jq, grep, tee) work out of the box
- Clean separation between logic and presentation
- Natural streaming support
- Easy testing via file I/O

Primary trade-off is added complexity for simple use cases, but the flexibility gained is substantial for real-world deployments.

---

**Next Steps**:
1. Review and discuss this proposal
2. Decide on open questions
3. If approved, create specs via spec-planner agent
4. Implement via core-development-expert agent