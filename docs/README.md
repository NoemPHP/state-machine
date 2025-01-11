# Noem State Machine

[![CI](https://github.com/NoemPHP/state-machine/actions/workflows/ci.yml/badge.svg)](https://github.com/NoemPHP/state-machine/actions/workflows/ci.yml)

![tmpdbpky87p](https://github.com/NoemPHP/state-machine/assets/4208996/2cebcf16-2548-4ff1-a09f-7ba35593b8b8)


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

### Practical example

This trivial example shows how a product may move through various states during processing:

```php
<?php

require 'vendor/autoload.php';

use Noem\State\RegionBuilder;

$orderId = 12345;

// Define the state machine
$region = (new RegionBuilder())
    // State configuration
    ->setStates('Checkout', 'Pending', 'Processing', 'Shipped', 'Delivered')
    ->markInitial('Checkout')
    ->markFinal('Delivered')

    // Context for this machine instance
    ->setRegionContext(['orderId' => $orderId])

    // Transitions
    // In a real application, these inspect the $trigger and allow/deny the transition based on it
    ->pushTransition(from: 'Checkout', to: 'Pending', guard: fn(object $trigger): bool => true)
    ->pushTransition(from: 'Pending', to: 'Processing', guard: fn(object $trigger): bool => true)
    ->pushTransition(from: 'Processing', to: 'Shipped', guard: fn(object $trigger): bool => true)
    ->pushTransition(from: 'Shipped', to: 'Delivered', guard: fn(object $trigger): bool => true)

    // Entry events
    ->onEnter(state: 'Pending', callback: function (object $trigger) {
        echo "[{$trigger->timestamp->format("Y-m-d H:i:s")}] Order {$this->get('orderId')} has been received and is {$this}.\n";
    })
    ->onEnter(state: 'Processing', callback: function (object $trigger) {
        echo "[{$trigger->timestamp->format("Y-m-d H:i:s")}] Order {$this->get('orderId')} is now {$this}.\n";
    })
    ->onEnter(state: 'Shipped', callback: function (object $trigger) {
        echo "[{$trigger->timestamp->format("Y-m-d H:i:s")}] Order {$this->get('orderId')} is {$this}.\n";
    })
    ->onEnter(state: 'Delivered', callback: function (object $trigger) {
        echo "[{$trigger->timestamp->format("Y-m-d H:i:s")}] Order {$this->get('orderId')} is {$this}.\n";
    })
    // Build the state machine
    ->build();

// Simulate order processing
$time = DateTimeImmutable::createFromFormat('U.u', microtime(true));

$region->trigger((object)['timestamp' => $time]);
$region->trigger((object)['timestamp' => $time->add(DateInterval::createFromDateString('1 day'))]);
$region->trigger((object)['timestamp' => $time->add(DateInterval::createFromDateString('2 day'))]);
$region->trigger((object)['timestamp' => $time->add(DateInterval::createFromDateString('3 day'))]);

```

### Documented example - Using `RegionBuilder`

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
### Notes about callbacks

#### Guard Functions:
Guard functions must return `true` or `false`.
If not provided, transitions are always allowed.
#### Callback and Action Functions:
These functions can modify state context.
They receive a trigger object as an argument.
#### Inheritance in Regions:
Inherited regions allow for shared configurations across multiple states or regions.
#### Initial and Final States:
 - `initial` specifies the starting state of the machine.
 - `final` specifies the terminal state where no further transitions are allowed.

### Events

Just like PSR-14, the event system filters relevant event callbacks by their parameter type.
This means you can -for example- only allow a transition when an Exception occurs, as seen above.

```php
$r = new RegionBuilder();
$r->setStates('on', 'running', 'error')
    ->pushTransition('on', 'error', function(\Throwable $exception){
        echo 'Error: '. $exception->getMessage();
        return true;
    })
;
```

However, it is also possible to use "named events", which provides a nice shortcut when using YAML definitions 
and greatly helps serializing application state. The syntax is a little more complex, though:

```php
$r = new RegionBuilder();
$r->setStates('one', 'two', 'three')
    ->pushTransition('one', 'two', fn(#[Name('hello-world')] Event $event): bool => true)
;
```
Here, the `Name` attribute works in tandem with the internal `Event` interface that mandates a name.
If the region encounters a callback written like this, it will only consider the callback if:
 * the `Event` type matches
 * and the `$event->name()` matches the value of `#[Name()]`


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
        $builder->onEnter($s, function (object $trigger) use (&$logs) {
            $logs[] = "ENTER: $this";
        });
        $builder->onExit($s, function (object $trigger) use (&$logs) {
            $logs[] = "EXIT: $this";
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
