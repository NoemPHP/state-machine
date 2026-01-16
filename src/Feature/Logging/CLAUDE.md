# LoggingFeature - Structured Logging via Chain Infrastructure

## Purpose

LoggingFeature provides **structured logging** through the Chain/Params middleware pattern. It enables ergonomic logging from state callbacks via `$this->log()` while allowing flexible log routing through middleware.

**Key Value**: Consistent logging API with pluggable handlers via LoggingChain.

## Dependencies

LoggingFeature works best with ExtendedState for the `$this->log()` helper:

```php
->enableFeatures(
    new ExtendedState(),    // For $this->log() binding
    new LoggingFeature()
)
```

Without ExtendedState, you can still use LoggingChain directly.

## Public API

### Context Helper: `$this->log()`

Log from within state callbacks:

```php
->onEnter('processing', function(object $trigger) {
    $this->log('info', 'Processing started', ['itemId' => $trigger->id]);
})

->onAction('processing', function(object $trigger) {
    try {
        processItem($trigger);
        $this->log('debug', 'Item processed successfully');
    } catch (\Exception $e) {
        $this->log('error', 'Processing failed', ['error' => $e->getMessage()]);
    }
})
```

**Parameters**:
- `level` (string): Log level (info, debug, warning, error, etc.)
- `message` (string): Log message
- `context` (array, optional): Additional context data

### LoggingChain

The chain that processes all log entries:

```php
$loggingChain = $chainMail->get(LoggingChain::class);

// Add middleware to handle logs
$loggingChain->link(function(LogParams $params, callable $next) {
    // Handle the log entry
    error_log("[{$params->level}] {$params->message}");

    // Pass to next handler
    return $next($params);
});
```

### LogParams

Encapsulates all log entry data:

```php
class LogParams {
    public readonly Region $region;        // Source region
    public readonly string $level;         // Log level
    public readonly string $message;       // Log message
    public readonly ?array $context;       // Optional context
    public readonly \DateTimeImmutable $timestamp;  // Auto-captured
    public readonly string $stateName;     // Current state name
}
```

## Usage Patterns

### Basic File Logging

```php
$loggingChain->link(function(LogParams $params, callable $next) {
    $line = sprintf(
        "[%s] [%s] [%s] %s %s\n",
        $params->timestamp->format('Y-m-d H:i:s'),
        strtoupper($params->level),
        $params->stateName,
        $params->message,
        $params->context ? json_encode($params->context) : ''
    );

    file_put_contents('/var/log/state-machine.log', $line, FILE_APPEND);

    return $next($params);
});
```

### PSR-3 Logger Integration

```php
use Psr\Log\LoggerInterface;

$loggingChain->link(function(LogParams $params, callable $next) use ($psrLogger) {
    $psrLogger->log(
        $params->level,
        $params->message,
        array_merge(
            $params->context ?? [],
            [
                'state' => $params->stateName,
                'timestamp' => $params->timestamp->format('c')
            ]
        )
    );

    return $next($params);
});
```

### Level Filtering

```php
$loggingChain->link(function(LogParams $params, callable $next) {
    // Only log warnings and errors
    $importantLevels = ['warning', 'error', 'critical', 'emergency'];

    if (in_array($params->level, $importantLevels)) {
        sendAlert($params->message, $params->context);
    }

    return $next($params);
});
```

### State-Specific Logging

```php
$loggingChain->link(function(LogParams $params, callable $next) {
    // Extra logging for critical states
    if ($params->stateName === 'payment_processing') {
        auditLog($params);
    }

    return $next($params);
});
```

### Buffered Batch Logging

```php
$buffer = [];

$loggingChain->link(function(LogParams $params, callable $next) use (&$buffer) {
    $buffer[] = $params;

    // Flush every 100 entries
    if (count($buffer) >= 100) {
        batchInsertLogs($buffer);
        $buffer = [];
    }

    return $next($params);
});
```

## Architecture

### Component Overview

```
LoggingFeature
    ├── Registers LoggingChain in ChainMail
    └── Binds $this->log() to BoundAccess (if ExtendedState loaded)

LoggingChain (extends Chain)
    └── Middleware stack for processing LogParams

LogParams
    ├── region: Region
    ├── level: string
    ├── message: string
    ├── context: ?array
    ├── timestamp: DateTimeImmutable (auto)
    └── stateName: string (from region.currentState())
```

### Log Flow

```
$this->log('info', 'message', [...])
    ↓
BoundAccess intercepts 'log' method call
    ↓
Creates LogParams with region, level, message, context
    ↓
LogParams dispatched through LoggingChain
    ↓
Each middleware processes/transforms/routes
    ↓
Final handlers (file, database, external service, etc.)
```

## Critical Idiosyncrasies

### 1. No Default Handler

LoggingFeature does NOT provide a default log handler:

```php
// ❌ Logs will be silently ignored without a handler
$this->log('info', 'This goes nowhere');

// ✅ Add a handler to process logs
$loggingChain->link(function(LogParams $params, callable $next) {
    error_log($params->message);
    return $next($params);
});
```

### 2. Requires ExtendedState for `$this->log()`

Without ExtendedState, `$this->log()` is not available:

```php
// ✅ Works - ExtendedState enables $this binding
->enableFeatures(new ExtendedState(), new LoggingFeature())

// ❌ $this->log() not available
->enableFeatures(new LoggingFeature())

// Alternative: Use LoggingChain directly
$loggingChain->call(new LogParams($region, 'info', 'message'));
```

### 3. StateName Captured at Creation

`LogParams.stateName` is captured when the log is created, not processed:

```php
// In 'processing' state
$this->log('info', 'Started');  // stateName = 'processing'

// Even if state changes before handler runs, stateName stays 'processing'
```

### 4. Context Must Be Array or Null

```php
// ✅ Valid context
$this->log('info', 'Message', ['key' => 'value']);
$this->log('info', 'Message', null);
$this->log('info', 'Message');  // Defaults to null

// ❌ Invalid - not an array
$this->log('info', 'Message', 'string context');
```

## Relationship to Other Features

| Feature | Relationship | Notes |
|---------|--------------|-------|
| **ExtendedState** | Optional | Enables `$this->log()` helper |
| **BoundAccess** | Uses | Method binding for context helper |
| **ChainMail** | Uses | LoggingChain registration |

## Files Reference

| File | Purpose |
|------|---------|
| `LoggingFeature.php` | Feature registration and BoundAccess binding |
| `LoggingChain.php` | Chain class for log middleware |
| `LogParams.php` | Log entry value object |

## Summary Checklist

When using LoggingFeature:

- [ ] Load ExtendedState for `$this->log()` helper
- [ ] Add at least one handler to LoggingChain
- [ ] Use appropriate log levels (info, debug, warning, error)
- [ ] Include context arrays for structured data
- [ ] Remember: no default handler—logs are silent without one
- [ ] StateName captured at log creation time

---

**Spec**: `specs/features/logging.yaml`
**Status**: Stable, production-ready
