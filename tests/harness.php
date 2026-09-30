<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

function check(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

function checkSame($expected, $actual, string $msg): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$msg} (expected " . var_export($expected, true)
            . ", got " . var_export($actual, true) . ")\n");
        exit(1);
    }
}
