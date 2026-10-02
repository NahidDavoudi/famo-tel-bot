<?php

namespace Illuminate\Contracts\Support;

interface MessageProvider
{
    /**
     * Get the messages for the instance.
     *
     *  \Illuminate\Contracts\Support\MessageBag
     */
    public function getMessageBag();
}
