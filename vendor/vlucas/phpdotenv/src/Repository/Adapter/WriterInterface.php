<?php

declare(strict_types=1);

namespace Dotenv\Repository\Adapter;

interface WriterInterface
{
    /**
     * Write to an environment variable, if possible.
     *
     * @param non-empty-string $name
     * @param string           $value
     *
     *  bool
     */
    public function write(string $name, string $value);

    /**
     * Delete an environment variable, if possible.
     *
     * @param non-empty-string $name
     *
     *  bool
     */
    public function delete(string $name);
}
