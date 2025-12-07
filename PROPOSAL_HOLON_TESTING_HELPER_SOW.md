# Statement of Work: Holon Testing Helper
## Convenience Helper for Testing Holon Instances

**Date**: 2025-12-07
**Version**: 1.0
**Author**: Claude Code AI Assistant
**Status**: Draft Proposal

---

## 📋 Executive Summary

This Statement of Work (SOW) proposes the development of a **Holon Testing Helper** - a convenience testing utility that enables efficient, spec-driven testing of entire state machine regions loaded via Holon's YAML configuration system. The helper will bridge the gap between specification-driven development and practical application-level testing by providing a standardized, developer-friendly interface for testing complete region behavior.

---

## 🎯 Objectives

- **Enable Application-Level Testing**: Provide a testing interface that makes it straightforward to load and test entire regions from YAML specifications
- **Maintain Spec-Driven Workflow**: Support the project's core philosophy of specification-first development
- **Standardize Testing Patterns**: Create consistent testing conventions for Holon-based region testing
- **Improve Developer Experience**: Reduce boilerplate code and simplify common testing scenarios

---

## 🔍 Current State Analysis

### Existing Infrastructure
- **Holon Feature**: Complete region bootstrap from YAML files
- **PHPUnit Integration**: Comprehensive test framework in place
- **YAML Specifications**: Standardized machine definitions in `/specs/` directory
- **Spec-Driven Pipeline**: Established workflows via `spec-planner` and `core-development-expert` agents

### Identified Gaps

1. **Testing Entry Point**: No standardized way to test complete regions from YAML
2. **Test Boilerplate**: Repetitive setup code for region loading and testing
3. **Assertion Shortcuts**: Missing convenience methods for common region testing patterns
4. **Transition Testing**: Limited tools for testing state machine behavior
5. **Event Verification**: No built-in support for verifying dispatched events

### Pain Points
- Developers must manually load YAML, create Holon instances, and set up testing infrastructure
- Complex setup code repeated across tests
- Difficult to verify complete region behavior without deep knowledge of internals
- Testing async behavior requires specialized handling

---

## 🏗️ Proposed Solution Overview

### Component Architecture

```
HolonTestHelper (Abstract Base Class)
├── YamlLoader: Loads and validates YAML specs
├── RegionBuilder: Creates test-ready regions
├── AssertionMethods: Convenience assertions
├── EventInterceptor: Captures and verifies events
├── StateInspector: Reads current state and metadata
└── AsyncSupport: Handles coroutine-based testing
```

### Key Features

1. **One-Line Setup**: Load complete regions from YAML with single method call
2. **Fluent Assertions**: Chainable methods for verifying state machine behavior
3. **Event Verification**: Capture and assert events dispatched during transitions
4. **State Inspection**: Easy access to current state, extended state, and region metadata
5. **Async Testing**: Built-in support for testing coroutine-based regions
6. **Transition Testing**: Declarative syntax for testing state changes

---

## 📊 Requirements Specification

### Functional Requirements

#### FR-1: YAML Loading & Region Creation
- **Description**: Load complete regions from YAML files with single method call
- **Acceptance Criteria**:
  - Support absolute and relative YAML file paths
  - Validate YAML syntax and structure
  - Return Holon instance with built region ready for testing
  - Handle YAML parsing errors gracefully

#### FR-2: Assertion Methods
- **Description**: Provide fluent API for common region testing assertions
- **Acceptance Criteria**:
  - `assertCurrentState(stateName)` - Verify current state
  - `assertEventDispatched(eventName, eventData)` - Verify event emission
  - `assertTransitionSuccess(originState, targetState)` - Verify state changes
  - `assertCanTrigger(triggerName)` - Verify trigger availability
  - `assertRegionState(condition)` - Custom assertion callbacks

#### FR-3: Event Testing
- **Description**: Capture and verify events during test execution
- **Acceptance Criteria**:
  - Event interception during region execution
  - Event collection and filtering
  - Event data verification with assert helpers
  - Support for event sequences and ordering

#### FR-4: Async Testing Support
- **Description**: Handle coroutine-based regions and async operations
- **Acceptance Criteria**:
  - Automatic coroutine resolution in test methods
  - Async assertion methods with timeouts
  - Support for testing async state transitions
  - Integration with existing AsyncFeature

#### FR-5: State Inspection
- **Description**: Provide access to region state and metadata
- **Acceptance Criteria**:
  - Current state name access
  - Extended state data retrieval
  - Region metadata access
  - ChainMail inspection
  - Active features enumeration

### Non-Functional Requirements

#### NFR-1: Performance
- Minimal overhead compared to manual region testing
- Efficient event interception and assertion processing
- Memory-efficient state inspection

#### NFR-2: Compatibility
- Full compatibility with existing PHPUnit test suite
- Support for all current RegionBuilder features
- Non-breaking integration with existing Holon functionality

#### NFR-3: Maintainability
- Clear separation of concerns
- Well-documented public API
- Extensible design for future Holon features

---

## 🔄 Implementation Strategy

### Phase 1: Core Framework (Weeks 1-2)
1. **Base Class Structure**: Create abstract `HolonTestHelper` base class
2. **YAML Integration**: Implement YAML loading with Holon
3. **Basic Assertions**: Core state and event assertion methods
4. **Documentation**: Initial API documentation and usage examples

### Phase 2: Advanced Features (Weeks 3-4)
1. **Event Testing**: Full event interception and verification
2. **Async Support**: Coroutine testing utilities
3. **State Inspection**: Complete state/metadata access
4. **Transition Testing**: Advanced transition assertion methods

### Phase 3: Testing & Integration (Week 5)
1. **Unit Tests**: Comprehensive test coverage for helper
2. **Integration Testing**: Test with existing YAML specifications
3. **Documentation**: Complete usage guide and examples
4. **Quality Gates**: Pass all quality checks (Psalm, PHP-CS, tests)

### Development Workflow
1. **Specification Creation**: Use `spec-planner` agent to create YAML specs
2. **Implementation**: Use `core-development-expert` agent for code implementation
3. **Testing**: Red-Green-Refactor cycle with comprehensive test coverage
4. **Quality Assurance**: Full quality pipeline including static analysis

---

## 📋 Testing Helper Distribution

### Composer Configuration
The Holon Testing Helper will be distributed as a development dependency:
- **Package Type**: `library`
- **Installation**: `composer require-dev project/holon-test-helper`
- **Scope**: Test-only code, not included in production codebase
- **Location**: All helper code resides in `/tests/Helper/` directory

### Dependency Management
- Helper code never ships in production builds
- All dependencies marked as `require-dev`
- Separated from main codebase for clear dev/test distinction

## 📁 File Structure

```
tests/Helper/
├── HolonTestHelper.php              # Main abstract base class
├── Exceptions/                      # Helper-specific exceptions
│   └── HolonTestException.php
├── Assertions/                      # Assertion helper classes
│   ├── StateAssert.php             # State testing assertions
│   ├── EventAssert.php             # Event testing assertions
│   ├── TransitionAssert.php        # Transition testing assertions
│   └── AsyncAssert.php             # Async testing assertions
├── Inspectors/                      # State inspection utilities
│   ├── StateInspector.php          # Current state access
│   ├── EventInspector.php          # Event monitoring
│   └── RegionInspector.php         # Region metadata access
├── Loaders/                         # YAML and region loading
│   ├── YamlLoader.php              # YAML file loading
│   └── HolonFactory.php            # Holon instance creation
└── Traits/                          # PHPUnit integration traits
    └── WithHolonAssertions.php      # PHPUnit assertion traits
```

---

## 🔧 Core Component Technical Implementation

### YamlLoader Technical Implementation

The `YamlLoader` handles YAML specification loading with sophisticated error handling and optimization:

**File Resolution Strategy:**
- **Base Path Calculation**: Uses `PROJECT_ROOT` environment variable or git root detection
- **Path Alias Resolution**: Supports `specs:machines/example.yaml` alias syntax → `specs/machines/example.yaml`
- **Include Path Support**: Resolves relative includes using parent directory as base
- **Path Validation**: Checks file existence and readability before parsing

**YAML Parsing Implementation:**
```php
// Pseudocode for parsing strategy
public function load(string $yamlPath): array
{
    $resolvedPath = $this->resolvePath($yamlPath);

    try {
        $yamlContent = $this->fileLoader->load($resolvedPath);
        $parsed = $this->yaml->parse($yamlContent, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);

        // Apply preprocessing (variable substitution, includes)
        $processed = $this->preprocessor->process($parsed, $resolvedPath);

        // Schema validation
        $this->validator->validate($processed, Schemas::HOLON_SPEC);

        return $processed;
    }
    catch (YamlParseException $e) {
        throw new HolonTestException(
            "YAML parse error in {$resolvedPath}:{$e->getParsedLine()}: {$e->getMessage()}"
        );
    }
    catch (SchemaValidationException $e) {
        throw new HolonTestException("Schema validation failed: " . $e->getMessage());
    }
}
```

**Preprocessing Engine:**
- **Variable Substitution**: Replace `${ENV:VARIABLE_NAME}` with environment values
- **File Includes**: Process `_include: specs/shared/actions.yaml` directives with circular dependency detection
- **Template Variables**: Handle mustache-style `{{variable}}` substitution
- **Validation Caching**: AST caching with file modification time tracking

**Performance Optimizations:**
- **AST Caching**: Parsed YAML cached in local PHP arrays during test run
- **Dependency Tracking**: Revalidation only when source files change
- **Lazy Loading**: Child YAML files loaded only when actually referenced

### HolonFactory Technical Implementation

The `HolonFactory` orchestrates complex Region construction with feature composition:

**RegionBuilder Construction Pipeline:**
```php
// Pseudocode for factory pattern
public function createRegion(array $yamlSpec): Region
{
    $builder = new RegionBuilder();

    // Phase 1: Feature Registration
    $this->loadFeatures($builder, $yamlSpec['features'] ?? []);

    // Phase 2: State Machine Structure
    $this->buildStateStructure($builder, $yamlSpec['states']);
    $this->configureTransitions($builder, $yamlSpec['transitions']);
    $this->addGuards($builder, $yamlSpec['guards']);

    // Phase 3: Service Injection
    $this->registerServices($builder, $yamlSpec['services'] ?? []);
    $this->configureExtendedState($builder, $yamlSpec['extended_state'] ?? []);

    // Phase 4: Middleware Stack Configuration
    $this->configureMiddleware($builder, $yamlSpec['middleware'] ?? []);

    // Phase 5: Initial State Setup
    $builder->markInitial($yamlSpec['initial_state']);
    $builder->markFinal($yamlSpec['final_state']);

    // Phase 6: Test Interceptors (for event capturing)
    $this->injectTestInterceptors($builder);

    return $builder->build();
}
```

**Feature Loading Algorithm:**
- **Dependency Resolution**: Features loaded in correct order using topological sort
- **Circular Dependency Detection**: Prevents infinite loops in feature dependencies
- **Factory Pattern**: Features instantiated via `FeatureRegistry::resolve()`
- **Configuration Injection**: Feature-specific settings passed through factory

**Service Registration Strategy:**
- **Singleton Pattern**: Services cached by class name/interface
- **Dependency Injection**: Automatic resolution of constructor parameters
- **Tagged Services**: Support for `"tag:my_service"` syntax for multiple implementations
- **Lifetime Management**: Service instance cleanup after test completion

**Middleware Chain Construction:**
- **Event Interceptor Injection**: Insert event capture middleware at chain start
- **Wrapper Pattern**: Test middleware wraps around user-defined middleware
- **Context Preservation**: Maintain middleware context across async boundaries

### Async Assertions Technical Implementation

Async testing requires sophisticated coroutine management with timeout handling:

**Coroutine Management Engine:**
```php
// Pseudocode for async assertion implementation
class AsyncAssertionManager
{
    private Generator $currentGenerator;
    private Scheduler $scheduler;
    private float $timeoutSeconds;

    public function triggerAsync(string $triggerName): AsyncAssertionResult
    {
        // Start async operation
        $this->region->trigger($triggerName);

        // Begin coroutine resolution loop
        $startTime = microtime(true);
        while (!$this->isCoroutineComplete()) {
            // Check for timeout
            if (microtime(true) - $startTime > $this->timeoutSeconds) {
                throw new TimeoutException("Async operation timed out after {$this->timeoutSeconds}s");
            }

            // Advance scheduler (handles enqueued coroutines)
            $this->scheduler->tick();

            // Yield control briefly to prevent busy-waiting
            usleep(1000); // 1ms sleep
        }

        // Capture final state and validate assertions
        return new AsyncAssertionResult(
            $this->getCurrentState(),
            $this->getCapturedEvents()
        );
    }
}
```

**Coroutine Resolution Algorithm:**
1. **Initial Trigger**: Execute trigger that starts async state action
2. **Generator Detection**: Check if action returns Generator (coroutine)
3. **Scheduler Integration**: Enqueue coroutine in AsyncFeature scheduler
4. **Event Loop Simulation**: Continuously advance scheduler until completion
5. **Yield Handling**: Process `yield` statements for async operations (fetch, sleep, etc.)
6. **Timeout Enforcement**: Track execution time and throw on timeout

**Advanced Yield Processing:**
- **Fetch Operations**: `yield AsyncFeature::fetch($url)` - wait for HTTP completion
- **Sleep Operations**: `yield AsyncFeature::sleep(5.0)` - wait for timer
- **File IO**: `yield AsyncFeature::load($path)` - wait for file operations
- **Nested Coroutines**: Handle generator that yields other generators
- **Exception Propagation**: Surface async exceptions properly

**Integration Challenges & Solutions:**
- **Event Loop Blocking**: Use microsecond sleeps between scheduler ticks
- **Memory Management**: Clear completed coroutine references to prevent leaks
- **Debugging Support**: Capture coroutine execution traces for test failures
- **Performance**: Optimize sleep duration for different async operation types

### Event Assertions Technical Implementation

Event capture requires middleware injection and sophisticated matching algorithms:

**Event Capture Middleware Implementation:**
```php
// Pseudocode for event interception
class EventCaptureMiddleware implements MiddlewareInterface
{
    private array $eventLog = [];

    public function handle(Mail $mail, Chain $chain): void
    {
        // Capture event before processing
        if ($mail instanceof EventMail) {
            $this->captureEvent([
                'name' => $mail->getEventName(),
                'data' => $mail->getEventData(),
                'timestamp' => microtime(true),
                'sequence' => ++$this->sequenceCounter,
                'source' => $this->getSourceContext()
            ]);
        }

        // Continue chain processing
        $chain->next($mail);

        // Post-processing capture (for modified events)
        if ($mail instanceof EventMail && $mail->isModified()) {
            $this->captureModifiedEvent($mail);
        }
    }

    private function captureEvent(array $event): void
    {
        $this->eventLog[] = $event;
        // Optional: real-time assertion checking
        $this->checkRealtimeAssertions($event);
    }
}
```

**Event Matching Algorithms:**

**Pattern-Based Matching:**
```php
// Support for wildcard patterns in event names
public function assertEventDispatched(string $pattern, array $expectedData = null): void
{
    $matches = array_filter($this->eventLog, function ($event) use ($pattern) {
        return fnmatch($pattern, $event['name']); // Supports * and ? wildcards
    });

    if (empty($matches)) {
        throw new AssertionException("No events matched pattern: $pattern");
    }

    if ($expectedData !== null) {
        $this->assertEventDataMatches($matches, $expectedData);
    }
}
```

**Sequence Verification (Complex Algorithm):**
- **Levenshtein Distance**: Account for interleaving events from async operations
- **Time Window Constraints**: Verify events occur within expected timeframes
- **Conditional Sequences**: Support patterns like "A then B only if C occurred"
- **State-Context Correlation**: Filter events based on region state when event occurred

**Assertion Modes:**
- **Strict Mode**: All other events must be explicitly ignored
- **Permissive Mode**: Extra events are allowed (default for testing)
- **Ordered Mode**: Events must occur in exact sequence
- **Unordered Mode**: Events can be in any order within constraints

**Performance Optimizations:**
- **Event Stream Processing**: Process events as stream (not storing all in memory)
- **Index-Based Lookup**: Build indexes on event names for fast pattern matching
- **Lazy Evaluation**: Only fully parse event data when explicitly asserted
- **Concurrent Event Capture**: Handle events dispatched from multiple regions simultaneously

---

## 🧪 Testing Strategy

### Test Coverage
- **Unit Tests**: Each helper class and method fully covered
- **Integration Tests**: Test helper with real YAML specifications
- **End-to-End Tests**: Test complete testing workflows

### Quality Assurance
- Psalm static analysis (Level 1 compliance)
- PHP-CS code style (PSR-12 compliance)
- PHPUnit test execution
- Manual testing with example machines

---

## 📋 Usage Examples

### Basic Usage
```php
class ExampleMachineTest extends PHPUnit_TestCase
{
    use WithHolonAssertions;

    public function testBasicStateTransition(): void
    {
        $helper = HolonTestHelper::fromYaml('specs/machines/example.yaml');

        $helper->assertCurrentState('initial')
               ->trigger('start')
               ->assertCurrentState('running')
               ->assertEventDispatched('machineStarted');
    }
}
```

### Async Testing
```php
class AsyncMachineTest extends PHPUnit_TestCase
{
    use WithHolonAssertions;

    public function testAsyncTransition(): void
    {
        $helper = HolonTestHelper::fromYaml('specs/features/async.yaml');

        $helper->triggerAsync('process')
               ->waitForCompletion(5.0)  // 5 second timeout
               ->assertCurrentState('completed')
               ->assertEventDispatched('processingFinished', ['result' => 'success']);
    }
}
```

### Event Testing
```php
class EventMachineTest extends PHPUnit_TestCase
{
    use WithHolonAssertions;

    public function testEventSequence(): void
    {
        $helper = HolonTestHelper::fromYaml('specs/machines/events.yaml');

        $helper->clearEventLog()
               ->trigger('complex_operation')
               ->assertEventSequence([
                   'operationStarted',
                   'subOperation1Completed',
                   'subOperation2Completed',
                   'operationFinished'
               ]);
    }
}
```

---

## 📅 Deliverables

### Primary Deliverables
1. **HolonTestHelper Base Class**: Complete implementation with all specified features
2. **YAML Processing Module**: Robust YAML loading and Holon instance creation
3. **Assertion Framework**: Comprehensive set of testing assertions
4. **Event Testing System**: Event interception and verification framework
5. **Async Testing Support**: Coroutine-based testing utilities
6. **Documentation**: Complete API documentation and usage guide

### Testing Deliverables
1. **Unit Test Suite**: 100% coverage of helper components
2. **Integration Tests**: Test helper with existing YAML specifications
3. **Usage Examples**: Sample test cases for common scenarios

### Documentation Deliverables
1. **API Documentation**: Complete PHPDoc documentation
2. **Usage Guide**: Step-by-step guide for developers
3. **Migration Guide**: How to migrate existing tests to use helper
4. **Best Practices**: Recommended patterns and conventions

---

## 🔍 Risk Assessment

### Technical Risks
- **Feature Compatibility**: Ensuring compatibility with all Holon features
- **Performance Overhead**: Minimizing testing framework performance impact
- **Async Complexity**: Properly handling coroutine testing edge cases

### Mitigation Strategies
- Comprehensive integration testing with existing features
- Performance benchmarking against manual testing
- Thorough testing of async scenarios and edge cases

---

## 👥 Stakeholder Approval

### Approval Requirements
- [ ] Technical Lead: Architecture and design review
- [ ] QA Lead: Testing strategy and coverage approval
- [ ] Development Team: Implementation feasibility review

### Sign-off Criteria
- [ ] All quality gates pass (Psalm, PHP-CS, Tests)
- [ ] Integration testing with existing machines successful
- [ ] Documentation complete and accessible
- [ ] Stakeholder approval obtained

---

## 📞 Communication Plan

### Progress Updates
- **Weekly**: Implementation progress and blockers
- **Milestone**: Major deliverable completion notifications
- **Issues**: Immediate notification of critical issues or delays

### Review Points
- **Design Review**: After Phase 1 completion
- **Code Review**: Continuous during implementation
- **Integration Testing**: Before Phase 3 completion

---

## 💡 Success Criteria

The Holon Testing Helper is successful when:
- ✅ Developers can load and test regions from YAML with minimal boilerplate
- ✅ All existing test patterns remain functional
- ✅ New tests are easier to write and maintain
- ✅ Quality pipeline passes consistently
- ✅ Documentation enables self-service adoption
- ✅ Performance impact is negligible compared to manual testing

---

**End of Statement of Work**