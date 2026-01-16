# Process Wrapper - AI Agent Instructions

## Overview

Wraps any Holon into a Unix-style process with file descriptors for IPC. This enables any language/tool that can read/write to sockets and FIFOs to interact with state machines.

## Key Components

| File | Purpose |
|------|---------|
| `holon.yml` | Main state machine with mode branching |
| `bootstrap.php` | PSR-4 autoloader for `ProcessWrapper\` namespace |
| `src/RuntimeDirectory.php` | Manages runtime dir, FIFOs, sockets |
| `src/BroadcastChannel.php` | Socket-based multi-client broadcast |
| `src/StreamParser.php` | IFS-delimited parsing with JSON detection |

## State Machine Design

The wrapper uses proper state-based mode handling:

```
detecting_mode ─┬─► setting_up_daemon ────┬─► setup_io ─► loading_holon ─► running ─► shutdown ─► done
                └─► setting_up_interactive─┘                                   │
                                                                               └─► error ─► shutdown
```

**Key principle**: Mode-specific logic is in separate states (`setting_up_daemon`, `setting_up_interactive`), shared logic in common states (`setup_io`, `loading_holon`, `running`).

## Design Decisions

1. **State-based mode branching**: No if/else for mode - branch to separate states
2. **Both modes create runtime dir**: Even interactive mode has sockets for observers
3. **LIFO transition order**: Fallback transitions registered first, specific guards last
4. **Shared running state**: Uses stored config (`stdin_is_tty`, `local_echo`) instead of checking mode

## Common Pitfalls

### LIFO Transition Order

Transitions are evaluated in **LIFO order** (last registered = checked first):

```yaml
# CORRECT: fallback first, specific second
transitions:
  - target: running                    # Checked LAST (fallback)
  - target: shutdown                   # Checked FIRST
    guard: !php |
      return fn(object $t): bool => $this->get('inner_complete', false);
```

```yaml
# WRONG: specific first, fallback second
transitions:
  - target: shutdown                   # Checked LAST (never reached!)
    guard: ...
  - target: running                    # Checked FIRST (always matches)
```

### Inner Holon Completion

The inner holon must be triggered twice per input to process transitions:

```php
$innerHolon->trigger((object)['raw_input' => $parsed]);  // Dispatch input
$innerHolon->trigger((object)[]);  // Process transitions
```

## Testing

```bash
# Piped mode (quick test)
echo -e "hello\nworld\nexit" | ddev exec php run.php machines/process-wrapper/holon.yml machines/string-reverse/holon.yml

# Interactive mode (requires TTY)
ddev ssh
php run.php machines/process-wrapper/holon.yml machines/string-reverse/holon.yml

# Daemon mode
ddev exec php run.php machines/process-wrapper/holon.yml -d machines/string-reverse/holon.yml &
# Then connect via sockets in /tmp/holon-{pid}/
```

## Not Spec-Driven

This component is NOT spec-driven because:
1. It's infrastructure, not business logic
2. Testing requires real I/O (TTY, sockets, FIFOs)
3. The helper classes are internal implementation details

## Future Work

- Windows support via named pipes
- Event loop with `$runtime->run(1)` stepping for true async
- Holon testing framework would enable SDD
