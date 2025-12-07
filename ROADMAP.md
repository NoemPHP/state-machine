# Roadmap

## Strategize a convenience helper for testing Holon instances.
In order to apply spec-driven development on application level, we need a convenient way to test entire Regions.
Holon provides us a way to create whole Regions off a single YAML files which gives us a nice standardized entrypoint for this.


## Converge middleware-test-runner and 'ddev atlas'
DDEV atlas is a wrapper/superset of 'machines/middleware-test-runner/machine.yml'. 
In the future, I want the machine to contain all functionality while 'ddev atlas' is just a loader for the machine and its parameters.
So we need to do the following:
* Refactor middleware-test-runner to be a self-contained Holon instance
* Introduce spec-driven-development: Map out all specs, write tests, then implement
* Move the spec discovery in 'ddev atlas' into the Holon after adding the specifications.
* Update the Holon to handle the parameters and conditionals (switch between single-spec and all-spec test execution)

It is important that we work on a copy of the machine so we do not break functionality until we are finished.

## Turn ParameterDeriver into a Chain in the Middleware

ParameterDeriver is a static helper class that is central to our event handling.
...tbd...

## Create SubscriptionFeature

We need a generic/extensible way for outside code to be notified of certain events within the Region.
A straightforward use case would be logging (transitions, events, etc.).
That way, we get a generic API for egress much like trigger() is our current API for ingress.
Due to the symmetrical scope, I think we should also use a PSR-14 like pattern of passing event listeners based on the parameter type.
Example: '$region->on(fn( LogEvent $e )=> $logger->log($e->message))' <-- This 
The on() method should return a $deregisterFunction() so that subscriptions can be removed again.

## Create Messaging pipeline
A more advanced use case of the subscriptions feature would be a message pipeline (Call -> Response) called MessageFeature.
At the highest level, this would simply be a mechanism that connects an incoming trigger() to an outgoing event.
We need an abstract "Message" object that transparently creates a UUID upon instantiation.
When a "Response" object is emitted, it must contain a reference to that UUID so that 
consuming code can map the received event to the initial signal.
The "Message" object should have a promise-like then() method. When it is dispatched to the Region, 
the MessageFeature can set up a temporary subscription and invoke the callback as soon as it sees a matching response.

## Create abilities API

Using the messaging system, we can add an API to make Regions carry out specific actions and return their results.
We need an AbilityRegistry and a contract for individual Ability objects. 
Relevant BuildStep and ConfigAccessor objects make it straightforward to configure abilities.

## Create conversation Feature

# Make Async callbacks explicit
Instead of implicitly being treated async by being a Generator, we should introduce an 'async: {}' object to the YAML spec.
The current paradigm of async actions mimicking synchronous execution (-> by always returning the last known yielded object) is cumbersome to maintain
and confusing to understand. By explicitly opting into async functionality, we can afford to set different expectations to it.
RegionBuilder's on*() methods must be replaced with BuildSteps that offer more configuration options.
Much like Meta MetaData and MetaType allow us to create and access arbitrary data pools to use in Features, we need a similar
mechanism for Callbacks: That way we can have one (default) channel for synchronous callbacks, and then a different channel
which is registered and maintained by the AsyncFeature.
The configuration options should cover things like debouncing/throttling and singleton behaviour (only enqueue new async task if the previous one has finished).
Nice to have: add an option to define the priority of a task (low-priority async callbacks get invoked by the coroutine scheduler less often)