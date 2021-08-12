# Noem State Machine
[![Testing](https://github.com/NoemPHP/state-machine/actions/workflows/testing.yml/badge.svg)](https://github.com/NoemPHP/state-machine/actions/workflows/testing.yml)

A finite state machine (FSM) implementation. It is built upon the interfaces declared in [NoemPHP/state-machine-interface](https://github.com/NoemPHP/state-machine-interface)

## Features
* **Hierarchical states** - If the active state has ascending "super-states", all of them are implicitly active as well.
* **Parallel states** - All children of an active parallel state are simultaneously active.
* **Guards** - Enable a given transition only when the specified event name matches or a given callback returns `true`.
* **Actions** - Dispatch actions to the machine to achieve stateful behaviour. Only the action handlers corresponding to the active state will get called.
* **Entry & Exit events** - Attach arbitrary subscribers to state changes.

## Installation
Install this package via composer:

`composer require noem/state-machine`

## Usage

### Using [noem/state-machine-loader](https://github.com/NoemPHP/state-machine-loader)
You can automatically configure a state machine instance from YAML, JSON or php arrays.
To make yourself familiar with the notation format, please refer to the documentation at the link above
```php
use Noem\State\Loader\YamlLoader;
use Noem\State\StateMachine;
use Noem\State\Transition\TransitionProvider;
use Noem\State\InMemoryStateStorage;

$yaml = <<<YAML
foo: 
  children:
    bar: {}
    baz: {}
YAML;

$loader = new YamlLoader($yaml);
$definitions = $loader->definitions();

$stateMachine = new StateMachine(
    $loader->transitions(), // Our generated TransitionProvider
    new InMemoryStateStorage($definitions->get('bar')) // 
);
// register the preconfigured action, onEntry, onExit event handlers
$stateMachine->attach($loader->observer());

var_dump($stateMachine->isInState('foo')); // true
var_dump($stateMachine->isInState('bar')); // also true
```