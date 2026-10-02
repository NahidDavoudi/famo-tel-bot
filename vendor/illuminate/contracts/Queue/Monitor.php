<?php

namespace Illuminate\Contracts\Queue;

interface Monitor
{
    /**
     * Register a callback to be executed when a daemon queue is starting.
     *
     * @param  mixed  $callback
     *  void
     */
    public function starting($callback);

    /**
     * Register a callback to be executed on every iteration through the queue loop.
     *
     * @param  mixed  $callback
     *  void
     */
    public function looping($callback);

    /**
     * Register a callback to be executed when a job fails after the maximum number of retries.
     *
     * @param  mixed  $callback
     *  void
     */
    public function failing($callback);

    /**
     * Register a callback to be executed when a daemon queue is stopping.
     *
     * @param  mixed  $callback
     *  void
     */
    public function stopping($callback);
}
