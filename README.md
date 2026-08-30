# mailtea-php

The official PHP SDK for [Mailtea](https://mailtea.app) — a thin, typed wrapper
over the [REST API](https://docs.mailtea.app/docs/api-reference). PHP 8.1+,
`ext-curl` and `ext-json`, no Composer dependencies.

## Install

```bash
composer require mailtea/mailtea
```

There is nothing to compile and nothing to autoload but this package, so a
project that does not use Composer can `require` the sources directly — see
[Local development](#local-development).

## Usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Mailtea\Mailtea;
use Mailtea\MailteaException;
use Mailtea\Request\SendEmail;

// Reads MAILTEA_API_KEY from the environment. Pass the key explicitly with
// new Mailtea($key) if you keep it somewhere else.
$mailtea = new Mailtea();

try {
    $sent = $mailtea->emails->send(new SendEmail(
        from: 'you@yourdomain.com',
        to: 'recipient@example.com',
        subject: 'Hello from Mailtea',
        html: '<p>Your first email, sent with <strong>Mailtea</strong>.</p>',
    ));

    echo $sent['id'], "\n";                      // txemail_…

    $email = $mailtea->emails->get($sent['id']);
    echo $email['status'], "\n";                 // queued, sent, delivered, …
} catch (MailteaException $e) {
    fwrite(STDERR, "Mailtea error (HTTP {$e->status}): {$e->getMessage()}\n");
}
```

Every method equally accepts a plain wire-format array — handy when you already
hold the JSON payload, or need a field the SDK does not model yet:

```php
$sent = $mailtea->emails->send([
    'from' => 'you@yourdomain.com',
    'to' => 'recipient@example.com',
    'subject' => 'Hello from Mailtea',
    'html' => '<p>Your first email.</p>',
    'idempotency_key' => 'order-1234',
]);
```

Responses come back as plain associative arrays in the API's own snake_case, so
what you read here is what the [API reference](https://docs.mailtea.app/docs/api-reference)
documents. The one exception is `suppressions->export()`, which returns raw CSV.

### Typed payloads

`Mailtea\Request` models the payloads you reach for every day, so your editor
completes them and a typo is a static error rather than a 400 from the server:

| Class | Used by |
| --- | --- |
| `SendEmail` | `emails->send()` |
| `BatchEmail` | `emails->batch()` |
| `UpdateEmail` | `emails->update()` |
| `CreateContact` · `UpdateContact` | `contacts->create()` / `update()` |
| `CreatePost` · `SendPost` · `SendTestPost` | `posts->create()` / `send()` / `sendTest()` |
| `CreateTopic` | `topics->create()` |

Everything else takes an array. That is deliberate: the long tail is mostly
update calls where an omitted key and a `null` one mean different things —
`['reply_to' => null]` clears the reply-to, leaving the key out keeps it — and a
typed object cannot express that distinction without inventing a sentinel.

### Sending

Set the From with exactly one of `from` (a plain address or `Name <address>`) or
`senderId` (a named, verified publication sender, which also supplies its default
reply-to). `to`, `cc`, `bcc` and `replyTo` each take a single address or a list.

One message can reach at most **50 recipients**, counted across `to`, `cc` and
`bcc` together — not 50 in each field. The API enforces it before anything is
sent. `reply_to` is not a recipient and does not count.

`scheduledAt` (ISO 8601) queues a send for later, and it stays cancellable until
it leaves the queue:

```php
$scheduled = $mailtea->emails->send(new SendEmail(
    from: 'you@yourdomain.com',
    to: 'recipient@example.com',
    subject: 'Your reminder',
    html: '<p>See you tomorrow.</p>',
    scheduledAt: gmdate('Y-m-d\TH:i:s\Z', time() + 3600),
));

$mailtea->emails->cancel($scheduled['id']);
```

Attachments carry base64 `content`; set a `content_id` (plus `content_type`) to
embed an inline image referenced by `cid:` in the HTML:

```php
$mailtea->emails->send(new SendEmail(
    from: 'you@yourdomain.com',
    to: 'recipient@example.com',
    subject: 'Your receipt',
    html: '<p>Thanks!</p><img src="cid:logo" />',
    tags: [['name' => 'category', 'value' => 'receipt']],
    attachments: [
        ['filename' => 'receipt.pdf', 'content' => $pdfBase64],
        ['filename' => 'logo.png', 'content' => $logoBase64,
         'content_type' => 'image/png', 'content_id' => 'logo'],  // inline
    ],
));
```

## Configuration

| Variable | What it does |
| --- | --- |
| `MAILTEA_API_KEY` | The API key, when none is passed to the constructor. A personal access token (`mt_pat_…`) or a service key (`mt_svc_…`). |
| `MAILTEA_API_BASE_URL` | Overrides the API base. Only needed for local dev or a self-hosted Mailtea; leave it unset in production. |

```php
$mailtea = new Mailtea(
    apiKey: getenv('MAILTEA_API_KEY') ?: null,
    baseUrl: 'http://127.0.0.1:7787',   // local dev; omit in production
);
```

The constructor throws when no key is available, rather than sending an
unauthenticated request whose 401 reads like a bad key instead of a missing one.

### A custom transport

`ext-curl` is the default. Implement `Mailtea\Transport` to route the SDK's calls
through your own HTTP client — a PSR-18 client, a Guzzle instance carrying your
retry middleware, or a fake that makes a test suite hermetic:

```php
use Mailtea\HttpResponse;
use Mailtea\Transport;

final class FakeTransport implements Transport
{
    public function send(string $method, string $url, array $headers, ?string $body): HttpResponse
    {
        return new HttpResponse(200, [], '{"id":"txemail_test"}');
    }
}

$mailtea = new Mailtea('mt_pat_test', 'https://api.mailtea.app', new FakeTransport());
```

A transport reports a non-2xx by returning it, never by throwing: the client needs
the body to read the API's `error`, `code` and `details` out of it. Only a genuine
transport failure — DNS, TLS, timeout — throws.

To change cURL's timeouts without replacing the transport, pass your own:
`new Mailtea($key, null, new Mailtea\CurlTransport(timeout: 60))`.

## API

| Method | Description |
| --- | --- |
| `emails->send($email)` | Send a transactional email → `['id' => …]` |
| `emails->batch($emails)` | Send up to 100 emails → `['data' => [['id' => …]]]` |
| `emails->get($id)` | Retrieve an email and its delivery status (adds a `status` alias of `last_event`) |
| `emails->list($params)` | List emails → `['data', 'total', 'limit', 'offset', 'has_more']` |
| `emails->update($id, $params)` | Reschedule a scheduled email |
| `emails->reschedule($id, $scheduledAt)` | Convenience wrapper over `update` |
| `emails->cancel($id)` | Cancel a scheduled email (`POST /cancel` — there is no DELETE) |
| `emails->analytics($params)` | Aggregate transactional metrics over an optional date window |
| `emails->inbound->list($params)` | List received emails in a publication (cursor-paginated) |
| `emails->inbound->get($id)` | Retrieve a received email with body, headers, and attachments |
| `emails->inbound->reply($id, $params)` | Reply to a received email (threads by construction) |
| `emails->inbound->attachments->list($id)` | List a received email's attachments (signed download URLs) |
| `emails->inbound->attachments->get($id, $attachmentId)` | Retrieve one inbound attachment |
| `contacts->create / upsert / list / get / update / delete` | Manage audience contacts (`upsert` = `create`; the endpoint upserts) |
| `posts->create($params)` | Create a newsletter post (draft, or `send: true`) → `['id' => …]` |
| `posts->send($id, $params)` | Send a draft post to the audience, now or scheduled |
| `posts->sendTest($id, $params)` | Send a `[TEST]` copy of a post → `['sent_to', 'failed_to']` |
| `posts->list / get / update / delete` | Manage posts (offset-paginated list) |
| `segments->create / list / get / update / delete` | Manage audience segments |
| `topics->create / list / get / update / delete` | Manage topic definitions (`visibility: 'public'` → shown on the reader preference page) |
| `senders->create / list / get / update / delete` | Manage named From identities (`email` immutable) |
| `assets->upload / uploadFile / list / delete` | The publication's image library (`upload` takes base64; `uploadFile` takes a path) |
| `templates->create / list / get / update / publish / unpublish / duplicate / delete` | Manage reusable email templates |
| `templates->render($params)` | Render a spec to HTML without saving → `['html', 'text']` |
| `templates->versions($id, $params)` | List a template's design history, newest first (metadata only) |
| `templates->restoreVersion($id, $version, $params)` | Put an older design back — a content write, so the template returns to **draft** |
| `suppressions->list / add / remove` | Manage the team-wide do-not-send list |
| `suppressions->export()` | Export the whole suppression list as CSV (raw text) |
| `domains->create / list / get / verify / update / delete` | Manage sending domains (add, read DNS records, verify) |
| `domains->tracking->create / list / verify / delete` | Manage CNAME tracking sub-domains under a domain |
| `webhooks->create / list / get / update / delete` | Manage outbound event subscriptions |
| `contactProperties->create / list / update / delete` | Manage custom contact fields (team-scoped) |
| `apiKeys->create / list / revoke` | Manage API keys (`settings:write`) |
| `automations->create / list / get / update / delete` | Manage automation graphs (`steps` + optional `connections`) |
| `automations->validate($params)` | Dry-run a graph → `['valid', 'issues']` (no automation needed) |
| `automations->activate / pause / archive` | Lifecycle (`cancel_runs` defaults **false** on pause, **true** on archive) |
| `automations->versions($id, …)` / `automations->version($id, $version, …)` | List stored versions; retrieve one with its graph |
| `automations->metrics($id, $params)` | Per-step funnel counts and branch splits (test runs excluded) |
| `automations->test($id, $params)` | One test run against a real contact — **sends real, billed email** |
| `automationRuns->list / get / cancel` | Inspect and cancel runs (a run pins the version it started on) |
| `events->send($params)` | Record a custom event → `['enrolled_automations', 'resumed_runs']` |
| `events->list($params)` | List recorded events (cursor-paginated) |
| `eventDefinitions->create / list / get / update / delete` | Manage the event catalog (`name` immutable) |

Audience resources (contacts, segments, topics, senders, templates, domains,
webhooks, automations, events, assets) are scoped to a publication: pass
`publication_id`. Suppressions and contact properties are team-scoped and take
none.

## Webhooks

Mailtea signs every outbound webhook with
[Standard Webhooks](https://www.standardwebhooks.com/). `WebhookSigning::verify()`
checks the signature and rejects replays. Pass the **raw** request body — not
re-encoded JSON, which reorders keys and breaks the signature — and the
endpoint's `whsec_…` signing secret:

```php
use Mailtea\WebhookSigning;

$raw = file_get_contents('php://input');
$headers = array_change_key_case(getallheaders(), CASE_LOWER);

$ok = WebhookSigning::verify(
    secret: $signingSecret,                        // whsec_… from webhooks->create()
    msgId: $headers['webhook-id'],
    timestamp: $headers['webhook-timestamp'],      // unix seconds
    payload: $raw,
    signatureHeader: $headers['webhook-signature'],
);

if (!$ok) {
    http_response_code(401);
    exit;
}

$event = json_decode($raw, true);
```

`verify()` returns `false` for every rejection there is — a bad signature, a
timestamp outside the five-minute tolerance, a malformed header — so a handler
needs one branch, not a try/catch. The comparison is constant-time.

`WebhookSigning::sign($secret, $msgId, $timestamp, $payload)` produces the same
header, which is how you fake a delivery in your own tests.

## Errors

Every failure is a `Mailtea\MailteaException`, so one catch covers the SDK:

```php
try {
    $mailtea->emails->send($email);
} catch (MailteaException $e) {
    $e->getMessage();  // "Validation failed" — the API's own message
    $e->status;        // 400; 0 when the request never got a response
    $e->errorCode;     // machine-readable code when one is available
    $e->details;       // the validation issues, on a 400
    $e->requestId;     // the API's x-request-id, worth quoting in a support ticket
}
```

Branch on `status` and `errorCode`, never on the message: the message is human
copy and changes without notice. `status === 0` means the request never reached
the API — a missing key, a DNS or TLS failure, a timeout, an unencodable body —
and `errorCode` then carries a client-side code such as `missing_api_key`.

## Local development

```bash
git clone https://github.com/mailtea-app/mailtea-php
cd mailtea-php
composer install    # nothing to download; this only writes the autoloader
composer test       # or: php tests/run.php
```

The suite needs no API key and makes no network calls: it runs against a mock
Mailtea on an ephemeral port on `127.0.0.1`, started under PHP's own built-in
server. `php tests/run.php` works on a bare checkout with no Composer at all.

To point the SDK at a Mailtea running on your machine:

```bash
export MAILTEA_API_BASE_URL="http://127.0.0.1:7787"
```

## License

MIT. See [LICENSE](LICENSE).
