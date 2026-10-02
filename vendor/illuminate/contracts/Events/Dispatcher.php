<?php

namespace Illuminate\Contracts\Events;

interface Dispatcher
{
    /**
     * Register an event listener with the dispatcher.
     *
     * @param  \Closure|string|array  $events
     * @param  \Closure|string|array|null  $listener
     *  void
     */
    public function listen($events, $listener = null);

    /**
     * Determine if a given event has listeners.
     *
     * @param  string  $eventName
     *  bool
     */
    public function hasListeners($eventName);

    /**
     * Register an event subscriber with the dispatcher.
     *
     * @param  object|string  $subscriber
     *  void
     */
    public function subscribe($subscriber);

    /**
     * Dispatch an event until the first non-null response is returned.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     *  mixed
     */
    public function until($event, $payload = []);

    /**
     * Dispatch an event and call the listeners.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     *  array|null
     */
    public function dispatch($event, $payload = [], $halt = false);

    /**
     * Register an event and payload to be fired later.
     *
     * @param  string  $event
     * @param  array  $payload
     *  void
     */
    public function push($event, $payload = []);

    /**
     * Flush a set of pushed events.
     *
     * @param  string  $event
     *  void
     */
    public function flush($event);

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param  string  $event
     *  void
     */
    public function forget($event);

    /**
     * Forget all of the queued listeners.
     *
     *  void
     */
    public function forgetPushed();
}
