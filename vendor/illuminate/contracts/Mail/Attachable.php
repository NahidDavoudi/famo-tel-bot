<?php

namespace Illuminate\Contracts\Mail;

interface Attachable
{
    /**
     * Get an attachment instance for this entity.
     *
     *  \Illuminate\Mail\Attachment
     */
    public function toMailAttachment();
}
