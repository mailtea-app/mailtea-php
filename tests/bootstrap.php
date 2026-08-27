<?php

declare(strict_types=1);

/**
 * Test support: an autoloader, a tiny assertion harness, and a real mock Mailtea
 * running under PHP's own built-in server.
 *
 * There is no test framework here on purpose. The SDK has no runtime
 * dependencies, and a suite that needs PHPUnit installed before it can tell you
 * whether the SDK works is a suite that does not run on a bare `git clone`.
 */

// Composer is optional: the SDK has nothing to install, so `composer install`
// only ever writes the autoloader. Fall back to a four-line PSR-4 loader when it
// has not been run.
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        if (!str_starts_with($class, 'Mailtea\\')) {
            return;
        }
        $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen('Mailtea\\'))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });
}

const MOCK_EMAIL_ID = 'txemail_00000000000000000000000000000000';

// --- the smallest harness that still reports properly ----------------------

final class Results
{
    public static int $passed = 0;
    public static int $failed = 0;

    /** @var list<string> */
    public static array $failures = [];
}

function test(string $name, callable $body): void
{
    try {
        $body();
        Results::$passed++;
        echo "PASS  {$name}\n";
    } catch (Throwable $e) {
        Results::$failed++;
        Results::$failures[] = $name;
        echo "FAIL  {$name}\n      {$e->getMessage()}\n";
    }
}

function assertSame(mixed $expected, mixed $actual, string $what): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s: expected %s, got %s',
            $what,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertTrue(bool $condition, string $what): void
{
    if (!$condition) {
        throw new RuntimeException($what);
    }
}

function assertContainsString(string $needle, string $haystack, string $what): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException("{$what}: {$haystack} does not contain {$needle}");
    }
}

/**
 * Assert a callable throws a MailteaException, and hand it back for further
 * assertions. A test that expects a throw and gets a return has to fail loudly,
 * which a bare try/catch quietly does not.
 */
function assertThrows(callable $body): Mailtea\MailteaException
{
    try {
        $body();
    } catch (Mailtea\MailteaException $e) {
        return $e;
    }

    throw new RuntimeException('Expected a MailteaException; nothing was thrown.');
}

// --- the mock API ----------------------------------------------------------

/**
 * The mock Mailtea, running as a separate process under PHP's built-in server.
 *
 * A real server rather than a stubbed transport, because the point of these
 * tests is the whole stack: the URL the SDK builds, the headers cURL sends, the
 * status the client reads back. A fake transport would pass while the SDK sent
 * `?status[]=queued` to an API that wanted `?status=queued`.
 */
final class MockMailtea
{
    /** @param resource $process */
    private function __construct(
        public readonly string $url,
        private $process,
        private readonly string $requestLog,
        private readonly string $serverLog,
    ) {
    }

    public static function start(): self
    {
        // The built-in server will not bind port 0, so claim an ephemeral port
        // with a throwaway socket and hand the number over.
        $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($probe === false) {
            throw new RuntimeException("Could not reserve a port: {$errstr} ({$errno})");
        }
        $port = (int) parse_url('tcp://' . stream_socket_get_name($probe, false), PHP_URL_PORT);
        fclose($probe);

        $requestLog = (string) tempnam(sys_get_temp_dir(), 'mailtea-php-requests-');
        $serverLog = (string) tempnam(sys_get_temp_dir(), 'mailtea-php-server-');

        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', __DIR__, __DIR__ . '/mock-mailtea.php'],
            [['pipe', 'r'], ['file', $serverLog, 'a'], ['file', $serverLog, 'a']],
            $pipes,
            null,
            // `+` keeps the LEFT operand on a key collision, so the overrides go
            // first and the shell's environment cannot displace them.
            ['MOCK_REQUEST_LOG' => $requestLog] + getenv()
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start the mock server.');
        }

        $server = new self("http://127.0.0.1:{$port}", $process, $requestLog, $serverLog);

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if (is_resource($socket)) {
                fclose($socket);

                return $server;
            }
            usleep(50_000);
        }

        $server->close();

        throw new RuntimeException(
            "Mock server never came up on port {$port}: " . (string) file_get_contents($serverLog)
        );
    }

    /** @return list<array<string, mixed>> */
    public function requests(): array
    {
        $lines = file($this->requestLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        /** @var list<array<string, mixed>> */
        return array_map(static fn (string $line): array => (array) json_decode($line, true), $lines);
    }

    /**
     * The most recent request, which is what most assertions want.
     *
     * @return array<string, mixed>
     */
    public function last(): array
    {
        $requests = $this->requests();
        if ($requests === []) {
            throw new RuntimeException('The mock server recorded no requests.');
        }

        return $requests[array_key_last($requests)];
    }

    public function close(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        @unlink($this->requestLog);
        @unlink($this->serverLog);
    }
}

/**
 * Assert the last request the mock saw. Every resource test is some version of
 * "did the SDK send this method to this path", so it is worth one helper.
 *
 * @param array<string, mixed>|null $body
 */
function assertRequest(
    MockMailtea $server,
    string $method,
    string $path,
    ?array $body = null,
    ?string $query = null,
): void {
    $request = $server->last();
    assertSame($method, $request['method'], 'method');
    assertSame($path, $request['path'], 'path');
    assertTrue(
        is_string($request['authorization'] ?? null)
            && str_starts_with($request['authorization'], 'Bearer '),
        'authorization header'
    );
    // Every request must name the SDK, so a support ticket can say which client
    // and which version produced the call.
    assertTrue(
        is_string($request['user_agent'] ?? null)
            && str_starts_with($request['user_agent'], 'mailtea-php/'),
        'user-agent header: ' . var_export($request['user_agent'] ?? null, true)
    );
    if ($body !== null) {
        assertSame($body, $request['body'], 'body');
    }
    if ($query !== null) {
        assertSame($query, $request['query'], 'query string');
    }
}
