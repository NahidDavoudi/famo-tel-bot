<?php

declare(strict_types=1);

namespace Doctrine\Inflector\Rules\Esperanto;

use Doctrine\Inflector\Rules\Pattern;

final class Uninflected
{
    /**  Pattern[] */
    public static function getSingular(): iterable
    {
        yield from self::getDefault();
    }

    /**  Pattern[] */
    public static function getPlural(): iterable
    {
        yield from self::getDefault();
    }

    /**  Pattern[] */
    private static function getDefault(): iterable
    {
        yield new Pattern('');
    }
}
