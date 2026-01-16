# Holon CLI Client

Interactive terminal client for Holon process-wrapper. Connects to running holon processes via Unix sockets and provides a rich terminal UI for handling interactions.

## Features

- Connects to process-wrapper runtime directory
- Handles all interaction types:
  - **Confirm**: Yes/No questions with keyboard navigation
  - **Select**: Single-choice selection with arrow keys
  - **Choice**: Multiple selection with space to toggle
  - **Prompt**: Free text input with validation
- Real-time stdout/stderr display
- Auto-detection of running holon processes

## Installation

```bash
cd clients/cli
npm install
npm run build
```

## Usage

### Start a Holon Process (Daemon Mode)

```bash
# Start the process-wrapper in daemon mode
php run.php machines/process-wrapper/holon.yml -d machines/string-transform/holon.yml &

# Note the PID or find the runtime directory
ls /tmp/holon-*
```

### Connect with CLI

```bash
# Connect by PID
npm start -- -p 12345

# Connect by runtime directory
npm start -- /tmp/holon-12345

# Auto-detect most recent holon process
npm start
```

### Development

```bash
# Watch mode for development
npm run dev

# In another terminal, run the compiled output
node dist/index.js -p 12345
```

## Architecture

```
src/
├── index.tsx              # Entry point with CLI parsing
├── App.tsx                # Main application component
├── components/
│   ├── InteractionHandler.tsx  # Routes to correct interaction component
│   ├── ConfirmInteraction.tsx  # Yes/No UI
│   ├── SelectInteraction.tsx   # Single selection UI
│   ├── ChoiceInteraction.tsx   # Multi-selection UI
│   └── PromptInteraction.tsx   # Text input UI
├── hooks/
│   └── useProcessWrapper.ts    # Socket connection management
└── types/
    └── interactions.ts         # TypeScript types for PHP classes
```

## Socket Protocol

The client connects to Unix domain sockets created by the process-wrapper:

| Socket | Direction | Purpose |
|--------|-----------|---------|
| `control.sock` | Bidirectional | Send input, receive output |
| `interactions.sock` | Receive | Interaction requests (JSON) |
| `stdout.sock` | Receive | Broadcast stdout |
| `stderr.sock` | Receive | Broadcast stderr |
| `logs.sock` | Receive | Structured log messages |

### Message Format

Interactions are JSON-serialized with newline delimiters:

```json
{
  "type": "Noem\\State\\Feature\\Interaction\\SelectRequest",
  "correlationId": "uuid-here",
  "data": {
    "question": "Choose an option",
    "options": {
      "a": { "label": "Option A", "description": "First option" },
      "b": { "label": "Option B", "description": "Second option" }
    },
    "defaultKey": "a"
  }
}
```

Responses are sent back in the same format:

```json
{
  "type": "Noem\\State\\Feature\\Interaction\\SelectResponse",
  "correlationId": "uuid-here",
  "data": {
    "selectedKey": "a",
    "cancelled": false
  }
}
```

## Keyboard Shortcuts

### Global
- `Ctrl+C` - Exit the client

### Confirm Interaction
- `←` `→` - Toggle Yes/No
- `y` / `n` - Quick select
- `Enter` - Submit
- `Esc` - Cancel

### Select Interaction
- `↑` `↓` - Navigate options
- `Enter` - Select and submit
- `Esc` - Cancel

### Choice Interaction
- `↑` `↓` - Navigate options
- `Space` - Toggle selection
- `Enter` - Submit selected items
- `Esc` - Cancel

### Prompt Interaction
- Type to enter text
- `Enter` - Submit
- `Esc` - Cancel

## Requirements

- Node.js >= 18
- A running holon process with process-wrapper
