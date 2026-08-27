<?php

declare(strict_types=1);

/**
 * Construction, configuration, and the shape of a failure. Everything here is
 * about the client itself rather than any one resource.
 *
 * @var MockMailtea $server
 * @var Mailtea\Mailtea $mailtea
 */

use Mailtea\HttpResponse;
use Mailtea\Mailtea;
use Mailtea\MailteaException;
use Mailtea\Transport;

/** Records what it was asked to send and answers whatever it was told to. */
final class RecordingTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string|null}> */
    public array $calls = [];

    public function __construct(private readonly HttpResponse $response)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body): HttpResponse
    {
        $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        return $this->response;
    }
}

test('a missing API key fails before any request is made', function () use ($server): void {
    $before = count($server->requests());
    $previous = getenv('MAILTEA_API_KEY');
    putenv('MAILTEA_API_KEY');

    try {
        $error = assertThrows(static fn () => new Mailtea(null, $server->url));
        assertSame(0, $error->status, 'status');
        assertSame('missing_api_key', $error->errorCode, 'error code');
        assertSame($before, count($server->requests()), 'requests made');
    } finally {
        if (is_string($previous)) {
            putenv('MAILTEA_API_KEY=' . $previous);
        }
    }
});

test('the API key and base URL are read from the environment', function (): void {
    putenv('MAILTEA_API_KEY=mt_pat_from_env');
    putenv('MAILTEA_API_BASE_URL=https://mailtea.internal.test/');

    try {
        $client = new Mailtea();
        // The trailing slash is stripped, or every path would carry a double one.
        assertSame('https://mailtea.internal.test', $client->baseUrl(), 'base URL');
    } finally {
        putenv('MAILTEA_API_KEY');
        putenv('MAILTEA_API_BASE_URL');
    }
});

test('an explicit base URL beats the environment', function (): void {
    putenv('MAILTEA_API_BASE_URL=https://from-the-shell.test');

    try {
        $client = new Mailtea('mt_pat_test_key', 'https://explicit.test');
        assertSame('https://explicit.test', $client->baseUrl(), 'base URL');
    } finally {
        putenv('MAILTEA_API_BASE_URL');
    }
});

test('the default base URL is the hosted API', function (): void {
    // Cleared explicitly: anyone working on Mailtea has this exported, and the
    // client is supposed to honour it. Relying on the case above having tidied
    // up only holds while these run in this order.
    $previous = getenv('MAILTEA_API_BASE_URL');
    putenv('MAILTEA_API_BASE_URL');

    try {
        assertSame('https://api.mailtea.app', (new Mailtea('mt_pat_test_key'))->baseUrl(), 'base URL');
        assertSame('https://api.mailtea.app', Mailtea::DEFAULT_BASE_URL, 'constant');
    } finally {
        if (is_string($previous)) {
            putenv('MAILTEA_API_BASE_URL=' . $previous);
        }
    }
});

test('an injected transport sees the bearer token, the User-Agent and the JSON body', function (): void {
    $transport = new RecordingTransport(new HttpResponse(200, [], '{"id":"txemail_1"}'));
    $client = new Mailtea('mt_pat_injected', 'https://api.mailtea.app', $transport);

    $client->emails->send(['from' => 'a@b.test', 'to' => 'c@d.test', 'subject' => 'Hi', 'html' => '<p>Hi</p>']);

    $call = $transport->calls[0];
    assertSame('POST', $call['method'], 'method');
    assertSame('https://api.mailtea.app/v1/emails', $call['url'], 'url');
    assertSame('Bearer mt_pat_injected', $call['headers']['Authorization'], 'authorization');
    assertSame('mailtea-php/' . Mailtea::VERSION, $call['headers']['User-Agent'], 'user agent');
    assertSame('application/json', $call['headers']['Content-Type'], 'content type');
    assertSame(
        '{"from":"a@b.test","to":"c@d.test","subject":"Hi","html":"<p>Hi</p>"}',
        $call['body'],
        'body'
    );
});

test('a GET sends no body and no Content-Type', function (): void {
    $transport = new RecordingTransport(new HttpResponse(200, [], '{"object":"list","data":[]}'));
    $client = new Mailtea('mt_pat_injected', 'https://api.mailtea.app', $transport);

    $client->emails->list();

    $call = $transport->calls[0];
    assertSame(null, $call['body'], 'body');
    assertTrue(!isset($call['headers']['Content-Type']), 'no Content-Type on a bodyless request');
});

test('a rejected request throws with the API status, message, details and request id', function () use ($mailtea): void {
    $error = assertThrows(static fn () => $mailtea->emails->send([
        'from' => 'a@b.test',
        'to' => 'c@d.test',
        // No subject and no content: the mock validates the way the API does.
    ]));

    assertSame(400, $error->status, 'status');
    assertSame('Validation failed', $error->getMessage(), 'message');
    assertSame('req_mock_00000000', $error->requestId, 'request id');
    assertTrue(is_array($error->details), 'details');
    assertSame(null, $error->errorCode, 'no code on this one');
});

test('a machine-readable error code is surfaced', function () use ($mailtea): void {
    $error = assertThrows(static fn () => $mailtea->emails->send([
        'from' => 'a@b.test',
        'to' => 'c@d.test',
        'subject' => '__mock_402__',
        'html' => '<p>Hi</p>',
    ]));

    assertSame(402, $error->status, 'status');
    // Branching on the code has to survive a copy change to the message.
    assertSame('marketing_plan_required', $error->errorCode, 'error code');
});

test('an unauthenticated request is a 401, not a silent success', function () use ($server): void {
    // The SDK always sends a bearer token, so reach past it to prove the mock
    // is actually checking — a mock that answers 200 to anything would let a
    // broken auth header ship.
    $context = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true]]);
    $response = file_get_contents($server->url . '/v1/emails', false, $context);

    assertTrue(is_string($response), 'a response body');
    assertContainsString('Unauthorized', (string) $response, 'the mock rejects a request with no token');
});

test('a 200 with an empty body comes back as an empty array', function () use ($mailtea, $server): void {
    // DELETE /v1/api-keys/{id} really does answer 200 with nothing in it.
    assertSame([], $mailtea->apiKeys->revoke('key_1'), 'revoke');
    assertRequest($server, 'DELETE', '/v1/api-keys/key_1');
});

test('a 404 throws rather than handing the error body back as a result', function (): void {
    $transport = new RecordingTransport(
        new HttpResponse(404, ['x-request-id' => 'req_404'], '{"error":"Not Found","path":"/v1/emails/gone"}')
    );
    $client = new Mailtea('mt_pat_test_key', 'https://api.mailtea.app', $transport);

    $error = assertThrows(static fn () => $client->emails->get('gone'));
    assertSame(404, $error->status, 'status');
    assertSame('Not Found', $error->getMessage(), 'message');
    assertSame('req_404', $error->requestId, 'request id');
});

test('a 3xx is a failure too, not a result', function (): void {
    // A misconfigured proxy answering 302 with an HTML body used to sail through
    // as success, because only 4xx and 5xx were checked.
    $transport = new RecordingTransport(new HttpResponse(302, [], '<html>Moved</html>'));
    $client = new Mailtea('mt_pat_test_key', 'https://api.mailtea.app', $transport);

    $error = assertThrows(static fn () => $client->emails->list());
    assertSame(302, $error->status, 'status');
    assertContainsString('HTTP 302', $error->getMessage(), 'message falls back to the status');
});

test('a 2xx body that is not JSON is a failure, not an empty result', function (): void {
    $transport = new RecordingTransport(new HttpResponse(200, [], '<html>gateway</html>'));
    $client = new Mailtea('mt_pat_test_key', 'https://api.mailtea.app', $transport);

    $error = assertThrows(static fn () => $client->emails->list());
    assertSame('invalid_response_body', $error->errorCode, 'error code');
});

test('a body that is not valid UTF-8 raises MailteaException, not JsonException', function () use ($mailtea): void {
    $error = assertThrows(static fn () => $mailtea->emails->send([
        'from' => 'a@b.test',
        'to' => 'c@d.test',
        // A latin-1 "é" that never went through mb_convert_encoding.
        'subject' => "Caf\xE9",
        'html' => '<p>Hi</p>',
    ]));

    assertSame(0, $error->status, 'status');
    assertSame('invalid_request_body', $error->errorCode, 'error code');
});

test('a transport failure is a MailteaException with status 0', function (): void {
    // Port 9 is the discard port: nothing listens, so the connection is refused
    // immediately rather than hanging for the timeout.
    $client = new Mailtea('mt_pat_test_key', 'http://127.0.0.1:9');
    $error = assertThrows(static fn () => $client->emails->list());

    assertSame(0, $error->status, 'status');
    assertContainsString('Request to Mailtea failed', $error->getMessage(), 'message');
});

test('the version constant and the VERSION file agree', function (): void {
    $file = trim((string) file_get_contents(__DIR__ . '/../VERSION'));
    // The sync script reads VERSION to tag the mirror; the SDK reports
    // Mailtea::VERSION in its User-Agent. If they drift, a support ticket names
    // a version that was never released.
    assertSame($file, Mailtea::VERSION, 'VERSION file vs Mailtea::VERSION');

    $composer = json_decode((string) file_get_contents(__DIR__ . '/../composer.json'), true);
    assertTrue(is_array($composer), 'composer.json parses');
    // Packagist derives the version from the git tag, so composer.json must NOT
    // pin one — a stale `version` key there silently overrides the tag.
    assertTrue(!isset($composer['version']), 'composer.json carries no version key');
});
