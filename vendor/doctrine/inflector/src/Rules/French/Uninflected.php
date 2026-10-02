<?php

declare(strict_types=1);

namespace Doctrine\Inflector\Rules\French;

use Doctrine\Inflector\Rules\Pattern;

final class Uninflected
{
    /**  Pattern[] */
    public static function getSingular(): iterable
    {
        yield from self::getDefault();

        yield new Pattern('bois');
        yield new Pattern('mas');
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
