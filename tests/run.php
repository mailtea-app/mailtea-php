<?php

declare(strict_types=1);

/**
 * The whole test suite. Run it with `composer test`, or `php tests/run.php` on a
 * bare checkout with no Composer at all.
 *
 * It needs no credentials and makes no network calls: every request goes to a
 * mock Mailtea on an ephemeral port on 127.0.0.1.
 */

require __DIR__ . '/bootstrap.php';

$server = MockMailtea::start();
register_shutdown_function(static fn () => $server->close());

$mailtea = new Mailtea\Mailtea('mt_pat_test_key', $server->url);

$files = glob(__DIR__ . '/*_test.php') ?: [];
sort($files);
foreach ($files as $file) {
    echo "\n" . basename($file, '_test.php') . "\n";
    require $file;
}

echo "\n" . Results::$passed . ' passed, ' . Results::$failed . " failed\n";
if (Results::$failed > 0) {
    foreach (Results::$failures as $failure) {
        echo "  - {$failure}\n";
    }
}

exit(Results::$failed === 0 ? 0 : 1);
