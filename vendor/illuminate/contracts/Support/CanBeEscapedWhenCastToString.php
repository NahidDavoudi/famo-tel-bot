<?php

namespace Illuminate\Contracts\Support;

interface CanBeEscapedWhenCastToString
{
    /**
     * Indicate that the object's string representation should be escaped when __toString is invoked.
     *
     * @param  bool  $escape
     *  $this
     */
    public function escapeWhenCastingToString($escape = true);
}
