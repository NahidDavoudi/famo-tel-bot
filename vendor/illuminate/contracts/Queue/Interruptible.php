<?php

namespace Illuminate\Contracts\Queue;

interface Interruptible
{
    /**
     * Handle a signal received by the queue worker.
     *
     * @param  int  $signal
     *  void
     */
    public function interrupted(int $signal): void;
}
