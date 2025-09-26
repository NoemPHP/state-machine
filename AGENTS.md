# Noem State Machine

Noem State Machine is a sophisticated PHP library implementing event-based finite state machines with support for hierarchical and parallel states, middleware, and extensive feature system.

## Project Architecture

### Core Components
- **`src/Region.php`** - Main state machine runtime engine
- **`src/RegionBuilder.php`** - Fluent API for building state machines
- **`src/Feature/`** - Modular feature system (AI, Async, Templates, etc.)
- **`src/Chains/`** - Chain of responsibility pattern for extensible processing
- **`machines/`** - Example state machine implementations

### Key Concepts
- **Regions**: Horizontal sets of states that can be hierarchical or parallel
- **Guards**: Predicates that enable/disable transitions
- **Actions**: Event handlers that execute business logic
- **Extended State**: Context data scoped to states or regions
- **Middleware**: Reusable modifications applied during machine construction

## Development Environment

### Prerequisites
- PHP 8.4+
- DDEV (development is done using DDEV containers)
- Composer

### Setup Commands
```bash
# All commands must be prefixed with 'ddev exec' to run inside the container
ddev exec composer install
ddev exec composer test
ddev exec composer cs:check
ddev exec composer psalm
```

### Running Examples
```bash
# Execute state machine examples
ddev exec php machines/frodos-journey/machine.php
ddev exec php machines/webserver/machine.php
```

## Testing & Quality

### Test Execution
```bash
# Run all tests
ddev exec composer test
ddev exec phpunit

# Watch tests (auto-rerun on changes)
ddev exec composer test:watch

# Run specific test
ddev exec phpunit --filter "TestClassName"
```

### Code Quality
```bash
# Check code style
ddev exec composer cs:check

# Fix code style
ddev exec composer cs:fix

# Static analysis
ddev exec composer psalm

# Run all quality checks
ddev exec composer quality
```

## Code Conventions

### PHP Standards
- **PHP 8.4+ features**: Use typed properties, union types, match expressions
- **PSR-4 autoloading**: `Noem\State\` namespace maps to `src/`
- **Strict types**: Always use `declare(strict_types=1);`
- **Return types**: Always declare return types on methods

### State Machine Patterns
```php
// Use RegionBuilder for fluent API construction
$region = (new RegionBuilder())
    ->setStates('initial', 'processing', 'final')
    ->markInitial('initial')
    ->markFinal('final')
    ->pushTransition('initial', 'processing', fn(object $trigger): bool => true)
    ->onEnter('processing', function(object $trigger) {
        // Entry callback logic
    })
    ->build();

// Guards should return boolean
->pushTransition('from', 'to', fn(object $trigger): bool => $trigger->isValid)

// Actions can return generators for async operations
->onAction('state', function(object $trigger): Generator {
    yield from $this->processAsync($trigger);
})
```

### YAML Configuration Format
```yaml
states:
  - name: state_name
    transitions:
      - target: next_state
        guard: !php return function($trigger): bool { return true; }
    onEnter:
      - run: !php return callbackFunction()
    action:
      - run: !php return actionFunction()
    regions:
      - states: [nested_states]
initial: initial_state
final: final_state
```

### Feature System
- Features extend `Noem\State\Feature\Feature`
- Implement `register(RegionBuilder $builder): void` method
- Use dependency injection through ChainMail container
- Features can require other features via `RequiresFeature` trait

## Key Directories

### `src/`
- **Core classes**: Region, RegionBuilder, Events, Connection
- **Feature/**: Modular feature implementations
  - **Ai/**: AI integration and templating
  - **Async/**: Asynchronous operation support
  - **ExtendedState/**: Context management
  - **Loader/**: YAML/configuration loading
  - **OrthogonalRegions/**: Parallel state support
- **Chains/**: Processing chain implementations
- **Middleware/**: Middleware system components

### `machines/`
- **frodos-journey/**: Complex example with AI integration and distance tracking
- **webserver/**: HTTP server state machine with connection spawning
- **coding/**: Development workflow state machine
- **directory-docs/**: Documentation generation example

### `tests/`
- **PHPUnit/**: Unit and integration tests
- **resources/**: Test fixtures and data

## Common Patterns

### Creating State Machines
1. **Simple Linear Flow**:
   ```php
   $builder = new RegionBuilder();
   $builder->setStates('start', 'middle', 'end')
           ->pushTransition('start', 'middle')
           ->pushTransition('middle', 'end');
   ```

2. **With Guards and Actions**:
   ```php
   ->pushTransition('from', 'to', fn($trigger): bool => $trigger->condition)
   ->onEnter('state', fn($trigger) => $this->handleEntry($trigger))
   ```

3. **Loading from YAML**:
   ```php
   $loader = new RegionLoader();
   $builder = $loader->fromYaml($yamlContent);
   ```

### Working with Features
```php
$region = (new RegionBuilder())
    ->pushFeature(new ExtendedState())
    ->pushFeature(new TemplateFeature())
    ->pushFeature(new AiFeature())
    // ... configure states and transitions
    ->build();
```

### Middleware Usage
```php
$middleware = function(RegionBuilder $builder, \Closure $next) {
    // Modify builder before construction
    $builder->eachState(fn($state) => $builder->onEnter($state, $callback));
    return $next($builder);
};

$builder->pushMiddleware($middleware);
```

## Dependencies & External Integrations

### Required Dependencies
- **symfony/yaml**: YAML configuration parsing
- **nette/schema**: Configuration validation
- **psr/container**: Dependency injection interface

### AI Features
- Uses template system with `{{#complete}}` directives for AI completion
- Integrates with various AI providers through template feature
- Supports streaming responses via PHP generators

### Testing Dependencies
- **phpunit/phpunit**: Testing framework
- **mockery/mockery**: Mocking library
- **spatie/phpunit-watcher**: Test watching

## Build & Deployment

### Composer Scripts
```bash
# Development workflow
ddev exec composer quality:fix  # Fix code style and run quality checks
ddev exec composer test:watch   # Watch tests during development

# CI/CD pipeline
ddev exec composer quality      # Run all quality checks (CI)
```

### File Structure Conventions
- One class per file following PSR-4
- Feature classes in `src/Feature/{FeatureName}/`
- Tests mirror source structure in `tests/PHPUnit/`
- Examples in `machines/{example-name}/`