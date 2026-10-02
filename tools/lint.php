<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$dirs = [$root . '/src', $root . '/public', $root . '/tools', $root . '/tests'];
$checked = 0;
$failures = 0;

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $checked++;
        $output = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            $failures++;
            echo implode("\n", $output) . "\n";
        }
    }
}

echo "linted {$checked} files, failures: {$failures}\n";
exit($failures === 0 ? 0 : 1);
