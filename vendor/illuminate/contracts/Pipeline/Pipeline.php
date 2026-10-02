<?php

namespace Illuminate\Contracts\Pipeline;

use Closure;

interface Pipeline
{
    /**
     * Set the object being sent through the pipeline.
     *
     * @param  mixed  $passable
     *  $this
     */
    public function send($passable);

    /**
     * Set the array of pipes.
     *
     * @param  mixed  $pipes
     *  $this
     */
    public function through($pipes);

    /**
     * Set the method to call on the pipes.
     *
     * @param  string  $method
     *  $this
     */
    public function via($method);

    /**
     * Run the pipeline with a final destination callback.
     *
     * @param  \Closure  $destination
     *  mixed
     */
    public function then(Closure $destination);
}
