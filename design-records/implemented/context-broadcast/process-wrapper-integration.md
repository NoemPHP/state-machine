# Context Broadcast - Process-Wrapper Integration

**Status**: Implemented
**Created**: 2026-01-16
**Related**: [README.md](./README.md)

## Overview

This document describes how to extend the process-wrapper machine to broadcast context changes over its socket infrastructure.

## Current Broadcasting Architecture

The process-wrapper already has a multi-channel broadcasting model:

| Channel | Type | Purpose |
|---------|------|---------|
| `stdin.fifo` | FIFO (write-only) | Input in daemon mode |
| `control.sock` | Socket (bidirectional) | Interactive control |
| `stdout.sock` | Socket (broadcast) | Standard output |
| `stderr.sock` | Socket (broadcast) | Error output |
| `interactions.sock` | Socket (broadcast) | InteractionRequest events |
| `logs.sock` | Socket (broadcast) | Structured log messages |

### Existing Subscription Pattern

In `loading_holon` state (holon.yml ~line 189):

```php
$innerHolon->on(function(object $event) use ($channels, $controlChannel, $localEcho): void {
    if (!$event instanceof InteractionRequest) {
        return;
    }

    // Serialize to JSON
    $json = json_encode($event) . "\n";

    // Broadcast to interactions channel
    if (isset($channels['interactions'])) {
        $channels['interactions']->broadcast($json);
    }

    // Also broadcast on control channel (for bidirectional clients)
    $controlChannel->broadcast($json);
});
```

## Proposed Extension: Context Channel

### New Channel: `context.sock`

Add a dedicated broadcast channel for context changes:

```
/tmp/holon-12345/
├── ...existing channels...
├── context.sock     # NEW: Unix socket for context changes
└── manifest.json    # Updated to include context channel
```

### Manifest Update

```json
{
  "channels": {
    "context": {
      "path": "/tmp/holon-12345/context.sock",
      "type": "unix",
      "direction": "read",
      "description": "Context state changes (Redux-like)"
    }
  },
  "capabilities": {
    "context_broadcast": true
  }
}
```

### Implementation in holon.yml

#### Step 1: Create Channel in `setup_io`

```yaml
- name: setup_io
  onEnter: !php |
    return function(object $t): void {
      // ... existing channel setup ...

      // NEW: Create context broadcast channel
      $channels['context'] = $runtimeDir->createChannel('context');

      $this->set('channels', $channels);
    };
```

#### Step 2: Subscribe to Inner Holon in `loading_holon`

```yaml
- name: loading_holon
  onEnter: !php |
    return function(object $t): \Generator {
      $innerHolon = $this->summon($holonPath);
      // ... existing setup ...

      $channels = $this->get('channels');
      $controlChannel = $this->get('control_channel');
      $localEcho = $this->get('local_echo');

      // NEW: Subscribe to context changes
      $innerHolon->on(function(ContextChange $event) use ($channels, $controlChannel, $localEcho): void {
        // Serialize to JSON
        $json = json_encode($event) . "\n";

        // Broadcast to context channel
        if (isset($channels['context'])) {
          $channels['context']->broadcast($json);
        }

        // Also broadcast on control channel (for bidirectional clients)
        $controlChannel->broadcast($json);

        // Local echo for debugging
        if ($localEcho) {
          echo "[CONTEXT] {$event->path}.{$event->key} = " . json_encode($event->value) . "\n";
        }
      });

      yield;
    };
```

### Client Usage Examples

#### Shell (socat)

```bash
HOLON_DIR="/tmp/holon-12345"

# Watch all context changes
socat - UNIX-CONNECT:"$HOLON_DIR/context.sock" | jq '.'

# Example output:
# {
#   "type": "Noem\\State\\Feature\\ExtendedState\\ContextChange",
#   "path": "processing/analyzing",
#   "key": "progress",
#   "value": 75,
#   "previousValue": 50,
#   "timestamp": 1705420800.123456
# }
```

#### Shell (with filtering)

```bash
# Watch only changes in 'workflow/' path
socat - UNIX-CONNECT:"$HOLON_DIR/context.sock" | \
  jq --unbuffered 'select(.path | startswith("workflow/"))'

# Watch only 'status' key changes
socat - UNIX-CONNECT:"$HOLON_DIR/context.sock" | \
  jq --unbuffered 'select(.key == "status")'
```

#### PHP Client

```php
$manifest = json_decode(file_get_contents('/tmp/holon-12345/manifest.json'), true);

// Connect to context channel
$context = stream_socket_client('unix://' . $manifest['channels']['context']['path']);
stream_set_blocking($context, true);

while ($line = fgets($context)) {
    $change = json_decode($line, true);

    printf(
        "[%s] %s.%s: %s → %s\n",
        date('H:i:s', (int)$change['timestamp']),
        $change['path'],
        $change['key'],
        json_encode($change['previousValue']),
        json_encode($change['value'])
    );
}
```

#### JavaScript/Node.js Client

```javascript
const net = require('net');
const fs = require('fs');

const manifest = JSON.parse(fs.readFileSync('/tmp/holon-12345/manifest.json'));
const socket = net.createConnection(manifest.channels.context.path);

let buffer = '';

socket.on('data', (data) => {
  buffer += data.toString();
  const lines = buffer.split('\n');
  buffer = lines.pop(); // Keep incomplete line

  for (const line of lines) {
    if (line.trim()) {
      const change = JSON.parse(line);
      console.log(`[${change.path}] ${change.key}:`, change.value);
    }
  }
});
```

#### Python Client

```python
import socket
import json

sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
sock.connect('/tmp/holon-12345/context.sock')

buffer = ''
while True:
    data = sock.recv(4096).decode()
    if not data:
        break
    buffer += data
    while '\n' in buffer:
        line, buffer = buffer.split('\n', 1)
        if line.strip():
            change = json.loads(line)
            print(f"[{change['path']}] {change['key']}: {change['value']}")
```

## Nested Region Considerations

### Inner Holon Path Visibility

When the inner holon has nested regions (via OrthogonalRegions or summon), the `path` field in `ContextChange` events should reflect the full hierarchy:

```
Process Wrapper
  └── Inner Holon (summoned)
        ├── workflow (state)
        │     └── nested region
        │           └── processing (state)  ← path: "workflow/processing"
        └── monitor (state)
```

### Event Bubbling Through Wrapper

If inner holon uses event bubbling, process-wrapper sees all events:

```
Inner Holon nested region: $this->set('progress', 75)
    ↓
ContextChange { path: "workflow/processing", key: "progress", value: 75 }
    ↓
Event bubbles to inner holon root
    ↓
Process-wrapper subscription receives event
    ↓
Broadcasts to context.sock
    ↓
External clients receive change notification
```

### Without Bubbling

If inner holon does NOT use event bubbling, process-wrapper must subscribe to each nested region:

```php
// In loading_holon
$innerHolon->on(function(ContextChange $event) use ($broadcast): void {
    $broadcast($event);
});

// Also subscribe to nested regions (if known)
foreach ($innerHolon->getNestedRegions() as $nested) {
    $nested->on(function(ContextChange $event) use ($broadcast): void {
        $broadcast($event);
    });
}
```

This is problematic for dynamically summoned regions inside the inner holon.

**Recommendation**: Implement event bubbling in ExtendedState so process-wrapper only needs one subscription point.

## Performance Considerations

### High-Frequency Updates

If the inner holon updates context rapidly (e.g., progress counters), the context channel may flood:

```php
// This would emit 100 events very quickly
for ($i = 0; $i < 100; $i++) {
    $this->set('counter', $i);
}
```

**Mitigations**:

1. **Debouncing**: Buffer changes and emit batches
2. **Sampling**: Only emit every Nth change
3. **Opt-in**: Make context broadcasting configurable

### Debouncing Implementation

```php
// In process-wrapper subscription
$pendingChanges = [];
$lastEmit = 0;
$debounceMs = 50; // 50ms debounce

$innerHolon->on(function(ContextChange $event) use (&$pendingChanges, &$lastEmit, $debounceMs, $channels): void {
    $pendingChanges[$event->key] = $event; // Latest value wins

    $now = microtime(true) * 1000;
    if ($now - $lastEmit >= $debounceMs) {
        $batch = new ContextChangeBatch(array_values($pendingChanges));
        $channels['context']->broadcast(json_encode($batch) . "\n");
        $pendingChanges = [];
        $lastEmit = $now;
    }
});
```

### Selective Broadcasting via Schema

The inner holon controls what gets broadcast using the schema-based serialization control (see main README):

```yaml
# In inner holon's configuration
context:
  broadcast: true     # Region-level control (default: true)
  schema:
    - name: progress
      type: integer
      # broadcast: true (default) - included in broadcasts

    - name: status
      type: string
      # broadcast: true (default) - included in broadcasts

    - name: userToken
      type: string
      broadcast: false   # Sensitive - excluded from broadcasts

    - name: internalCache
      type: object
      broadcast: false   # Complex - excluded from broadcasts
```

**How it works with process-wrapper**:
1. Inner holon only emits `ContextChange` events for broadcast-enabled properties
2. Process-wrapper receives pre-filtered events
3. No additional filtering needed in process-wrapper (already handled at source)

This is cleaner than filtering in process-wrapper because:
- Single point of configuration (in the holon, not the wrapper)
- No accidental exposure of sensitive data
- Consistent behavior regardless of which wrapper hosts the holon

### Additional Process-Wrapper Filtering (Optional)

For extra security, process-wrapper can add a second filter layer:

```php
// Wrapper-level redaction for defense-in-depth
$sensitiveKeys = ['password', 'token', 'secret', 'api_key'];

$innerHolon->on(function(ContextChange $event) use ($sensitiveKeys, $channels): void {
    // Extra safety: redact anything that slipped through
    if (in_array($event->key, $sensitiveKeys)) {
        $event = new ContextChange(
            $event->path,
            $event->key,
            '[REDACTED]',
            '[REDACTED]',
            $event->timestamp
        );
    }
    $channels['context']->broadcast(json_encode($event) . "\n");
});
```

## Security Considerations

### Socket Permissions

Context data may contain sensitive information. Ensure:

```php
// RuntimeDirectory creates sockets with restricted permissions
$socket = stream_socket_server("unix://{$path}", $errno, $errstr);
chmod($path, 0600);  // Owner read/write only
```

## Testing

### Unit Test: Context Broadcast Emission

```php
public function testContextChangesAreBroadcast(): void
{
    // Setup process-wrapper with mock inner holon
    $wrapper = $this->createProcessWrapper();
    $contextSocket = $this->connectToContextChannel($wrapper);

    // Trigger inner holon context change
    $wrapper->getInnerHolon()->trigger((object)[
        'type' => 'test',
        'action' => fn($t) => $t->set('counter', 42)
    ]);

    // Verify broadcast received
    $line = fgets($contextSocket);
    $change = json_decode($line, true);

    $this->assertEquals('counter', $change['key']);
    $this->assertEquals(42, $change['value']);
}
```

### Integration Test: Multi-Client Broadcast

```php
public function testMultipleClientsReceiveBroadcast(): void
{
    $wrapper = $this->createProcessWrapper();

    // Connect multiple clients
    $client1 = $this->connectToContextChannel($wrapper);
    $client2 = $this->connectToContextChannel($wrapper);

    // Trigger change
    $wrapper->getInnerHolon()->trigger((object)[
        'action' => fn($t) => $t->set('shared', 'value')
    ]);

    // Both clients receive
    $change1 = json_decode(fgets($client1), true);
    $change2 = json_decode(fgets($client2), true);

    $this->assertEquals($change1, $change2);
}
```

## Summary

Adding context broadcasting to process-wrapper involves:

1. **New channel**: `context.sock` for dedicated context change streaming
2. **Subscription**: Subscribe to inner holon's `ContextChange` events
3. **Serialization**: JSON encoding with newline delimiter
4. **Filtering**: Optional path/key filtering for performance and security
5. **Documentation**: Update manifest and client examples

This creates a Redux-like observable state pattern accessible to any language/tool that can read Unix sockets.