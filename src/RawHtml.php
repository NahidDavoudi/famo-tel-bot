<?php
declare(strict_types=1);

namespace App;

final class RawHtml
{
    public function __construct(public readonly string $html) {}
}
