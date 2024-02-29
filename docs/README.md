# Noem State Machine

[![CI](https://github.com/NoemPHP/state-machine/actions/workflows/ci.yml/badge.svg)](https://github.com/NoemPHP/state-machine/actions/workflows/ci.yml)

A finite state machine (FSM) implementation. It is built upon the interfaces declared
in [NoemPHP/state-machine-interface](https://noemphp.github.io/state-machine-interface)

## Features

* **Nested regions** - One horizontal set of states is called a "region". However, each state can have any number of sub-regions
* **Guards** - Enable a given transition only a predicate returns `true`.
* **Actions** - Dispatch actions to the machine to achieve stateful behaviour. Only the action handlers corresponding to
  the active state will get called.
* **Entry & Exit events** - Attach arbitrary subscribers to state changes.
* **Region & State context** - 

## Installation

Install this package via composer:

`composer require noem/state-machine`

## Usage

### Using `RegionBuilder`

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
