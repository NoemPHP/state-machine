# Noem State Machine

[![CI](https://github.com/NoemPHP/state-machine/actions/workflows/ci.yml/badge.svg)](https://github.com/NoemPHP/state-machine/actions/workflows/ci.yml)

This library provides an implementation of a Finite State Machine (FSM) in PHP.
The benefits of using an FSM architecture include:

1. **Simplified system behavior modeling:** State machines help to represent and organize the behavior of a system in a structured and understandable manner.
2. **Ease of refactoring:** Significant changes to system architecture can be done without affecting business logic
3. **Testability:** State machines allow for easier testing of individual states and transitions, making it simpler to isolate and test specific system behaviors.
4. **Reduced complexity:** By breaking down a complex system into smaller, manageable states, state machines can simplify the overall system design and make it easier to understand. 
5. **Predictable behavior:** State machines ensure that a system behaves consistently and predictably, as the transitions between states are explicitly defined. 
6. **Documentation:** State machines serve as a form of documentation, as they provide a visual/textual representation of the system's behavior and transitions

## Features

* **Nested regions**: One horizontal set of states is called a "region". However, each state can have any number of sub-regions, allowing both *parallel states* and *hierarchical states*
* **Guards**: Enable a given transition only if a predicate returns `true`.
* **Actions**: Dispatch actions to the machine to achieve stateful behaviour. Only the action handlers corresponding to
  the active state will get called.
* **Entry & Exit events**: Attach arbitrary subscribers to state changes.
* **Region & State context**: Store data relevant to the current application state. Data can be scoped for an individual state - or shared with the entire region
* **State inheritance**: Since regions can be nested, each region can request specific data to be passed down from the parent region.
* **Middleware**: Before creating the final machine, your can augment your definitions with reusable middlewares.

## Installation

Install this package via composer:

`composer require noem/state-machine`

## Usage

### Using `RegionBuilder`

The `RegionBuilder` in Noem State Machine is a class used for constructing and configuring finite state machines. 
It allows developers to define states, transitions, guards, entry and exit events and actions 
within a state machine, making it convenient for implementing stateful behavior in applications.

```php
<?php

declare(strict_types=1);

use Noem\State\RegionBuilder;

$r = (new RegionBuilder())
        // Define all possible states
        ->setStates('off', 'starting', 'on', 'error')
        // if not called, will default to the first entry
        ->markInitial('off')
        // if not called, will default to the last entry
        ->markFinal('error')
        // Define a transition from one state to another
        // <FROM> <TO> <PREDICATE>
        ->pushTransition('off', 'starting', fn(object $trigger):bool => true)
        // no predicate means always true 
        ->pushTransition('starting', 'on') 
        ->pushTransition('on', 'error', function(\Throwable $exception){
            echo 'Error: '. $exception->getMessage();
            return true;
        })
        // Add a callback that runs whenever the specified state is entered
        ->onEnter('starting', function(object $trigger){
            echo 'Starting application';
        })
        ->onAction('on',function (object $trigger){
            // TODO: Main business logic
            echo $trigger->message;
        })
        ->build(); // returns the actual Region object
            
while(!$r->isFinal()){
    $r->trigger((object)['message'=>'hello world']);
}
```
### Using `RegionLoader`

You can also load a state machine configuration from YAML. `RegionLoader::fromYaml()` will provide
a `RegionBuilder` which you can then modify further or start using right away.
Here is an example:

```yaml
states:
  - name: one
    transitions:
      - target: two
  - name: two
    regions:
       states:
        - name: one_one
          transitions:
            - target: one_two
        - name: one_two
          transitions:
            - target: one_three
        - name: one_three
    transitions:
      - target: three
  - name: three
initial: one
final: three
```

This configuration can be loaded like this:

```php
<?php

declare(strict_types=1);

use Noem\State\RegionLoader;

$yaml = file_get_contents('./path/to/machine.yaml');
$builder = (new RegionLoader())->fromYaml($yaml);
$builder->pushMiddleware(/** more on that in the next chapter */)->build();

```

### Middleware

It is easy to think of common & repetitive concerns that are portable from one machine to the other, for example
* **Logging**: Keeping track of any state change by adding a listener on each entry/exit event
* **Exception handling**: Adding an error state as well as a transition to it whenever an exception is caught
* **Re/Store state**: Serialize the machine context and restore it when it is reinitialized

For this scenario, `RegionBuilder` offers support for middlewares that can make arbitrary changes
to a machine before it is built.

This example shows a simple logging middleware:

```php
<?php

declare(strict_types=1);

use Noem\State\RegionBuilder;

$logs = [];
$middleware = function (RegionBuilder $builder, \Closure $next) use (&$logs) {
    $builder->eachState(function (string $s) use ($builder, &$logs) {
        $builder->onEnter($s, function (object $trigger) use ($s, &$logs) {
            $logs[] = "ENTER: $s";
        });
        $builder->onExit($s, function (object $trigger) use ($s, &$logs) {
            $logs[] = "EXIT: $s";
        });
    });

    return $next($builder);
};

$region = (new RegionBuilder())
    ->setStates('foo', 'bar')
    ->pushTransition('foo', 'bar')
    ->pushMiddleware($middleware)
    ->build();
```
