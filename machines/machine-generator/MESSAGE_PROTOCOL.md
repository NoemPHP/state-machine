# Machine Generator - Message Protocol

## Overview

The machine-generator is a **headless** holon that generates state machines based on natural language descriptions. It communicates via structured messages with any consumer (CLI, web UI, API, etc.).

## Message Format

All messages follow this structure:

```php
[
    'type' => string,           // Message type identifier
    'correlation_id' => string, // Unique ID for request-response tracking
    'timestamp' => int,         // Unix timestamp
    'payload' => array,         // Type-specific data
]
```

## Message Types

### 1. GenerateRequest (Consumer → Generator)

**Purpose**: Initiate machine generation

```php
[
    'type' => 'generate.request',
    'correlation_id' => 'req-12345',
    'timestamp' => time(),
    'payload' => [
        'description' => 'Create a todo list app',
        'qa_history' => [],  // Optional: previous Q&A pairs
    ],
]
```

### 2. AnalysisComplete (Generator → Consumer)

**Purpose**: Report requirement analysis results

```php
[
    'type' => 'analysis.complete',
    'correlation_id' => 'req-12345',
    'timestamp' => time(),
    'payload' => [
        'confidence' => 0.85,
        'complexity' => 'moderate',
        'missing_info' => ['State transition logic', 'Data persistence'],
        'needs_clarification' => true,
    ],
]
```

### 3. QuestionRequest (Generator → Consumer)

**Purpose**: Request clarification from user

```php
[
    'type' => 'question.request',
    'correlation_id' => 'req-12345',
    'timestamp' => time(),
    'payload' => [
        'question' => 'Should the todo list support categories?',
        'context' => 'moderate',  // Complexity level for context
    ],
]
```

### 4. QuestionResponse (Consumer → Generator)

**Purpose**: Provide answer to clarifying question

```php
[
    'type' => 'question.response',
    'correlation_id' => 'req-12345',
    'timestamp' => time(),
    'payload' => [
        'answer' => 'Yes, with nested subcategories',
        'cancel' => false,  // true to abort generation
    ],
]
```

### 5. ProgressUpdate (Generator → Consumer)

**Purpose**: Report generation progress

```php
[
    'type' => 'progress.update',
    'correlation_id' => 'req-12345',
    'timestamp' => time(),
    'payload' => [
        'stage' => 'planning',  // planning | generating_yaml | generating_php | validating
        'message' => 'Creating implementation plan...',
        'percent' => 40,  // Optional: 0-100
    ],
]
```

### 6. ValidationResult (Generator → Consumer)

**Purpose**: Report validation results

```php
[
    'type' => 'validation.result',
    'correlation_id' => 'req-12345',
    'timestamp' => time(),
    'payload' => [
        'passed' => false,
        'errors' => [
            'YAML syntax error: mapping values not allowed (line 19)',
            'Missing initial state',
        ],
        'retry_count' => 1,
        'max_retries' => 3,
    ],
]
```

### 7. GenerationComplete (Generator → Consumer)

**Purpose**: Report successful generation

```php
[
    'type' => 'generation.complete',
    'correlation_id' => 'req-12345',
    'timestamp' => time(),
    'payload' => [
        'machine_name' => 'todo-list',
        'directory' => '/var/www/html/machines/todo-list',
        'files' => [
            'holon.yml' => 2048,      // File size in bytes
            'holon-functions.php' => 4096,
        ],
        'inline_functions' => false,
    ],
]
```

### 8. GenerationFailed (Generator → Consumer)

**Purpose**: Report generation failure

```php
[
    'type' => 'generation.failed',
    'correlation_id' => 'req-12345',
    'timestamp' => time(),
    'payload' => [
        'reason' => 'max_retries_exceeded',  // max_retries_exceeded | ai_error | validation_error | user_cancelled
        'message' => 'Could not generate valid YAML after 3 attempts',
        'errors' => [...],  // Detailed error list
        'suggestions' => [
            'Try simplifying your request',
            'Be more specific about states',
        ],
    ],
]
```

## Message Flow Examples

### Successful Generation (No Questions)

```
Consumer → Generator: GenerateRequest
Generator → Consumer: AnalysisComplete (confidence: 0.85, needs_clarification: false)
Generator → Consumer: ProgressUpdate (planning)
Generator → Consumer: ProgressUpdate (generating_yaml)
Generator → Consumer: ProgressUpdate (validating)
Generator → Consumer: GenerationComplete
```

### Generation with Clarification

```
Consumer → Generator: GenerateRequest
Generator → Consumer: AnalysisComplete (confidence: 0.60, needs_clarification: true)
Generator → Consumer: QuestionRequest
Consumer → Generator: QuestionResponse
Generator → Consumer: AnalysisComplete (confidence: 0.85, needs_clarification: false)
Generator → Consumer: ProgressUpdate (planning)
...
Generator → Consumer: GenerationComplete
```

### Generation with Retries

```
Consumer → Generator: GenerateRequest
Generator → Consumer: AnalysisComplete
Generator → Consumer: ProgressUpdate (planning)
Generator → Consumer: ProgressUpdate (generating_yaml)
Generator → Consumer: ProgressUpdate (validating)
Generator → Consumer: ValidationResult (passed: false, retry: 1/3)
Generator → Consumer: ProgressUpdate (planning)  # Retry
...
Generator → Consumer: ValidationResult (passed: true)
Generator → Consumer: GenerationComplete
```

### Failed Generation

```
Consumer → Generator: GenerateRequest
Generator → Consumer: AnalysisComplete
Generator → Consumer: ProgressUpdate (planning)
Generator → Consumer: ProgressUpdate (validating)
Generator → Consumer: ValidationResult (retry: 1/3)
Generator → Consumer: ValidationResult (retry: 2/3)
Generator → Consumer: ValidationResult (retry: 3/3)
Generator → Consumer: GenerationFailed (reason: max_retries_exceeded)
```

## Implementation Notes

### Message Correlation

- `correlation_id` links requests with responses
- Consumer generates unique ID for each request
- Generator includes same ID in all related messages

### Async Message Handling

- Generator sends messages asynchronously during generation
- Consumer should handle messages in event loop
- Use MessageFeature's correlation tracking for pairing

### Error Handling

- Generator never throws exceptions - always sends GenerationFailed
- Consumer responsible for displaying errors appropriately
- Validation errors include specific line numbers when available

### Cancellation

- Consumer can cancel via QuestionResponse with `cancel: true`
- Generator sends GenerationFailed with `reason: user_cancelled`

### Extension Points

- Additional message types can be added for progress reporting
- Payload can include extra fields without breaking compatibility
- Message type uses namespaced format (`category.action`)
