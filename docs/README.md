# Noem State Machine

[![CI](https://github.com/NoemPHP/state-machine/actions/workflows/ci.yml/badge.svg)](https://github.com/NoemPHP/state-machine/actions/workflows/ci.yml)

his library provides an implementation of a Finite State Machine (FSM) for developers 
to manage complex systems with multiple states and transitions. 
The benefits of using an FSM pattern include clearer system behavior, easier testing, 
improved reusability, and simplified debugging. FSMs -and Noem State Machine in particular - 
enable developers to model and control the behavior of a system by defining 
states, transitions, guards, actions, entry and exit events, and nested regions.

## Features

* **Nested regions** - One horizontal set of states is called a "region". However, each state can have any number of sub-regions
* **Guards** - Enable a given transition only if a predicate returns `true`.
* **Actions** - Dispatch actions to the machine to achieve stateful behaviour. Only the action handlers corresponding to
  the active state will get called.
* **Entry & Exit events** - Attach arbitrary subscribers to state changes.
* **Region & State context** - Store data relevant to the current application state. Data can be scoped for an individual state - or shared with the entire region
* **State inheritance** - Since regions can be nested, each region can request specific data to be passed down from the parent region.
* **Middleware** - Before creating the final machine, your can augment your definitions with reusable middlewares

## Installation

Install this package via composer:

`composer require noem/state-machine`

## Usage

### Using `RegionBuilder`

`RegionBuilder` in Noem State Machine is a class used for constructing and configuring finite state machines. 
It allows developers to define states, transitions, guards, entry and exit events and actions 
within a state machine, making it convenient for implementing stateful behavior in applications.

```php
<?php

declare(strict_types=1);

use Noem\State\RegionBuilder;

$r = (new RegionBuilder())
        ->setStates(['off', 'starting', 'on', 'error'])
        ->markInitial('off') // if not called, will default to the first entry
        ->markFinal('error') // if not called, will default to the last entry
        ->pushTransition('off', 'starting', fn(object $trigger)=>true)
        ->pushTransition('starting', 'on', fn(object $trigger)=>true)
        ->pushTransition('on', 'error', function(\Throwable $exception){
            echo 'Error: '. $exception->getMessage();
            return true;
        })
        ->onEnter('starting', function(object $trigger){
            echo 'Starting application';
        })
        ->onAction('on',function (object $trigger){
            echo $trigger->message;
            // TODO: Main business logic
        })
        ->build();
            
while(!$r->isFinal()){
    $r->trigger((object)['message'=>'hello world']);
}
```
### Using `RegionLoader`

You can also load a state machine configuration from YAML. `RegionLoader::fromYaml()` will provide
a `RegionBuiler` which you can then modify further or start using right away.
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

$loader = (new RegionLoader())->fromYaml($yaml);

```

### Middleware

It is easy to think of common & repetitive concerns that are portable from one machine to the other, for example
* **Logging** - Keeping track of any state change by adding a listener on each entry/exit event
* **Exception handling** - Adding an error state as well as a transition to it whenever an exception is caught
* **Re/Store state** - Serialize the machine context and restore it when it is reinitialized

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
    ->setStates(['foo', 'bar'])
    ->pushTransition('foo', 'bar')
    ->pushMiddleware($middleware)
    ->build();

```
