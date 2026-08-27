<?php

declare(strict_types=1);

/**
 * A stand-in for the Mailtea API, so the SDK's tests run with no credentials and
 * no network.
 *
 * It is the router script for PHP's own built-in server:
 *
 *     php -S 127.0.0.1:<port> tests/mock-mailtea.php
 *
 * Its behaviour is ported from `examples/.shared/node/mock-mailtea.mjs` in the
 * Mailtea monorepo — check auth first, record every request, answer the real
 * routes with the real shapes — and extended to cover every endpoint the SDK
 * reaches.
 *
 * Two rules keep a mock honest, and both are load-bearing here:
 *
 * 1. It never answers a route the API does not have. Cancel is
 *    `POST /v1/emails/:id/cancel`; there is no `DELETE` on emails. A mock that
 *    invents one turns a green suite into a 404 in production.
 * 2. It never invents a field. `POST /cancel` returns the object and the id and
 *    nothing else, because that is all the real endpoint returns.
 *
 * The server is a separate process, so it records every request as one JSON line
 * in the file named by MOCK_REQUEST_LOG. That file is what the assertions read.
 */

const MOCK_EMAIL_ID = 'txemail_00000000000000000000000000000000';

/** Ask the mock for a specific failure. Only the tests ever send these. */
const TRIGGER_PLAN_ERROR = '__mock_402__';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
$query = parse_url($uri, PHP_URL_QUERY);
$query = is_string($query) ? $query : '';

$headers = array_change_key_case(getallheaders(), CASE_LOWER);
$raw = (string) file_get_contents('php://input');
$body = $raw === '' ? null : json_decode($raw, true);

$log = getenv('MOCK_REQUEST_LOG');
if (is_string($log) && $log !== '') {
    file_put_contents(
        $log,
        json_encode([
            'method' => $method,
            'path' => $path,
            'query' => $query,
            'authorization' => $headers['authorization'] ?? null,
            'user_agent' => $headers['user-agent'] ?? null,
            'content_type' => $headers['content-type'] ?? null,
            'body' => $body,
            'raw' => $raw,
        ]) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/** @param array<mixed>|string|null $payload */
function reply(int $status, array|string|null $payload, string $contentType = 'application/json'): void
{
    http_response_code($status);
    header('Content-Type: ' . $contentType);
    // The real API stamps one on every response, and the SDK is expected to
    // carry it into the exception it throws.
    header('x-request-id: req_mock_00000000');
    if ($payload === null) {
        return;
    }
    echo is_string($payload) ? $payload : (string) json_encode($payload);
}

/** @return array<string, mixed> */
function listOf(array $data = []): array
{
    return [
        'object' => 'list',
        'data' => $data,
        'total' => count($data),
        'limit' => 20,
        'offset' => 0,
        'has_more' => false,
    ];
}

/** @return array<string, mixed> */
function cursorList(array $data = []): array
{
    return ['object' => 'list', 'data' => $data, 'has_more' => false, 'next_cursor' => null];
}

/** @return array<string, mixed> */
function entity(string $type, string $id, array $extra = []): array
{
    return ['object' => $type, 'id' => $id, ...$extra];
}

// Auth is checked first, the same way the real API does it — a client that
// forgets the key should fail its test, not silently "send".
$authorization = $headers['authorization'] ?? null;
if (!is_string($authorization) || !str_starts_with($authorization, 'Bearer ')) {
    reply(401, ['error' => 'Unauthorized']);

    return;
}

/**
 * The route table, in order. First match wins, so the fixed paths
 * (`/v1/emails/analytics`) sit above the parameterised ones (`/v1/emails/{id}`).
 *
 * @var list<array{0: string, 1: string, 2: callable}> $routes
 */
$routes = [
    // --- emails ------------------------------------------------------------
    ['POST', '#^/v1/emails$#', static function (array $m) use ($body): void {
        if (($body['subject'] ?? null) === TRIGGER_PLAN_ERROR) {
            reply(402, [
                'error' => 'Your plan covers transactional email only.',
                'code' => 'marketing_plan_required',
            ]);

            return;
        }
        // The real API validates before it sends and returns the issues in
        // `details`. Mirroring that keeps the error tests honest.
        $hasSender = isset($body['from']) || isset($body['sender_id']);
        $hasContent = isset($body['html']) || isset($body['text']) || isset($body['template']);
        if (!$hasSender || !isset($body['to']) || !isset($body['subject']) || !$hasContent) {
            reply(400, [
                'error' => 'Validation failed',
                'details' => [['path' => ['subject'], 'message' => 'Required']],
            ]);

            return;
        }
        reply(200, ['id' => MOCK_EMAIL_ID]);
    }],
    ['POST', '#^/v1/emails/batch$#', static function (array $m) use ($body): void {
        $items = is_array($body) ? $body : [];
        reply(200, [
            'data' => array_map(
                static fn (int $index): array => ['id' => 'txemail_' . str_pad((string) $index, 32, '0', STR_PAD_LEFT)],
                array_keys($items)
            ),
        ]);
    }],
    ['GET', '#^/v1/emails/analytics$#', static function (): void {
        reply(200, [
            'object' => 'email_analytics',
            'total' => 2,
            'delivered' => 2,
            'bounced' => 0,
            'from_date' => '2026-08-01T00:00:00.000Z',
        ]);
    }],
    // Inbound sits under /v1/emails/, so it has to come before the {id} routes.
    ['GET', '#^/v1/emails/inbound$#', static fn () => reply(200, cursorList())],
    ['GET', '#^/v1/emails/inbound/([^/]+)/attachments$#', static function (array $m): void {
        reply(200, ['object' => 'list', 'data' => [
            ['object' => 'inbound_attachment', 'id' => 'inatt_1', 'download_url' => 'https://example.test/a'],
        ]]);
    }],
    ['GET', '#^/v1/emails/inbound/([^/]+)/attachments/([^/]+)$#', static function (array $m): void {
        reply(200, entity('inbound_attachment', $m[2], ['download_url' => 'https://example.test/a']));
    }],
    ['GET', '#^/v1/emails/inbound/([^/]+)$#', static function (array $m): void {
        reply(200, entity('inbound_email', $m[1], ['subject' => 'Mock inbound', 'text' => 'hi']));
    }],
    ['POST', '#^/v1/emails/inbound/([^/]+)/reply$#', static function (array $m): void {
        reply(200, ['id' => MOCK_EMAIL_ID, 'status' => 'queued']);
    }],
    ['GET', '#^/v1/emails$#', static fn () => reply(200, listOf())],
    ['GET', '#^/v1/emails/([^/]+)$#', static function (array $m): void {
        reply(200, entity('email', $m[1], [
            'last_event' => 'delivered',
            'subject' => 'Mock email',
            'created_at' => '2026-01-01T00:00:00.000Z',
        ]));
    }],
    ['PATCH', '#^/v1/emails/([^/]+)$#', static fn (array $m) => reply(200, entity('email', $m[1]))],
    // Cancel is a POST. The real endpoint answers with the object and the id and
    // nothing else — no `last_event`.
    ['POST', '#^/v1/emails/([^/]+)/cancel$#', static fn (array $m) => reply(200, entity('email', $m[1]))],

    // --- contacts ----------------------------------------------------------
    ['POST', '#^/v1/contacts$#', static function () use ($body): void {
        reply(200, entity('contact', 'con_1', ['email' => $body['email'] ?? null, 'status' => 'active']));
    }],
    ['GET', '#^/v1/contacts$#', static fn () => reply(200, cursorList())],
    ['GET', '#^/v1/contacts/([^/]+)$#', static fn (array $m) => reply(200, entity('contact', $m[1]))],
    ['PATCH', '#^/v1/contacts/([^/]+)$#', static function (array $m) use ($body): void {
        reply(200, entity('contact', $m[1], ['status' => $body['status'] ?? 'active']));
    }],
    ['DELETE', '#^/v1/contacts/([^/]+)$#', static fn (array $m) => reply(200, entity('contact', $m[1], ['deleted' => true]))],

    // --- segments / topics / senders ---------------------------------------
    ['POST', '#^/v1/segments$#', static fn () => reply(200, entity('segment', 'seg_1'))],
    ['GET', '#^/v1/segments$#', static fn () => reply(200, cursorList())],
    ['GET', '#^/v1/segments/([^/]+)$#', static fn (array $m) => reply(200, entity('segment', $m[1]))],
    ['PATCH', '#^/v1/segments/([^/]+)$#', static fn (array $m) => reply(200, entity('segment', $m[1]))],
    ['DELETE', '#^/v1/segments/([^/]+)$#', static fn (array $m) => reply(200, entity('segment', $m[1], ['deleted' => true]))],

    ['POST', '#^/v1/topics$#', static function () use ($body): void {
        reply(200, entity('topic', 'top_1', [
            'name' => $body['name'] ?? null,
            'default_subscription' => $body['default_subscription'] ?? null,
        ]));
    }],
    ['GET', '#^/v1/topics$#', static fn () => reply(200, cursorList())],
    ['GET', '#^/v1/topics/([^/]+)$#', static fn (array $m) => reply(200, entity('topic', $m[1]))],
    ['PATCH', '#^/v1/topics/([^/]+)$#', static fn (array $m) => reply(200, entity('topic', $m[1]))],
    ['DELETE', '#^/v1/topics/([^/]+)$#', static fn (array $m) => reply(200, entity('topic', $m[1], ['deleted' => true]))],

    ['POST', '#^/v1/senders$#', static fn () => reply(200, entity('sender', 'snd_1'))],
    ['GET', '#^/v1/senders$#', static fn () => reply(200, cursorList())],
    ['GET', '#^/v1/senders/([^/]+)$#', static fn (array $m) => reply(200, entity('sender', $m[1]))],
    ['PATCH', '#^/v1/senders/([^/]+)$#', static fn (array $m) => reply(200, entity('sender', $m[1]))],
    ['DELETE', '#^/v1/senders/([^/]+)$#', static fn (array $m) => reply(200, entity('sender', $m[1], ['deleted' => true]))],

    // --- posts -------------------------------------------------------------
    ['POST', '#^/v1/posts$#', static fn () => reply(200, ['id' => 'post_1'])],
    ['GET', '#^/v1/posts$#', static fn () => reply(200, ['data' => [], 'total' => 0])],
    ['POST', '#^/v1/posts/([^/]+)/send$#', static fn (array $m) => reply(200, ['id' => $m[1], 'status' => 'sending'])],
    ['POST', '#^/v1/posts/([^/]+)/test$#', static function () use ($body): void {
        reply(200, ['sent_to' => $body['recipients'] ?? [], 'failed_to' => []]);
    }],
    ['GET', '#^/v1/posts/([^/]+)$#', static fn (array $m) => reply(200, entity('post', $m[1]))],
    ['PATCH', '#^/v1/posts/([^/]+)$#', static fn (array $m) => reply(200, entity('post', $m[1]))],
    ['DELETE', '#^/v1/posts/([^/]+)$#', static fn (array $m) => reply(200, entity('post', $m[1], ['deleted' => true]))],

    // --- templates ---------------------------------------------------------
    ['POST', '#^/v1/templates/render$#', static fn () => reply(200, ['html' => '<p>Hi</p>', 'text' => 'Hi'])],
    ['POST', '#^/v1/templates$#', static fn () => reply(200, entity('template', 'tpl_1'))],
    ['GET', '#^/v1/templates$#', static fn () => reply(200, cursorList())],
    ['POST', '#^/v1/templates/([^/]+)/publish$#', static fn (array $m) => reply(200, entity('template', $m[1], ['status' => 'published']))],
    ['POST', '#^/v1/templates/([^/]+)/unpublish$#', static fn (array $m) => reply(200, entity('template', $m[1], ['status' => 'draft']))],
    ['POST', '#^/v1/templates/([^/]+)/duplicate$#', static fn (array $m) => reply(200, entity('template', 'tpl_copy'))],
    ['GET', '#^/v1/templates/([^/]+)/versions$#', static function (): void {
        reply(200, ['object' => 'list', 'data' => [['version' => 2, 'is_current' => true]], 'retention' => ['max_versions' => 20]]);
    }],
    ['POST', '#^/v1/templates/([^/]+)/versions/([^/]+)/restore$#', static function (array $m): void {
        reply(200, [
            'restored' => true,
            'restored_from_version' => (int) $m[2],
            'unpublished' => true,
            'template' => entity('template', $m[1], ['status' => 'draft']),
        ]);
    }],
    ['GET', '#^/v1/templates/([^/]+)$#', static fn (array $m) => reply(200, entity('template', $m[1]))],
    ['PATCH', '#^/v1/templates/([^/]+)$#', static fn (array $m) => reply(200, entity('template', $m[1]))],
    ['DELETE', '#^/v1/templates/([^/]+)$#', static fn (array $m) => reply(200, entity('template', $m[1], ['deleted' => true]))],

    // --- suppressions ------------------------------------------------------
    ['GET', '#^/v1/suppressions/export$#', static function (): void {
        reply(
            200,
            "email,reason,source,created_at\nblocked@example.test,bounce,automatic,2026-01-01T00:00:00.000Z\n",
            'text/csv; charset=utf-8'
        );
    }],
    ['GET', '#^/v1/suppressions$#', static fn () => reply(200, cursorList())],
    ['POST', '#^/v1/suppressions$#', static function () use ($body): void {
        reply(200, ['added' => count($body['emails'] ?? [])]);
    }],
    ['DELETE', '#^/v1/suppressions$#', static function () use ($body): void {
        reply(200, ['removed' => count($body['emails'] ?? [])]);
    }],

    // --- assets ------------------------------------------------------------
    ['POST', '#^/v1/assets$#', static function () use ($body): void {
        reply(200, entity('asset', 'ast_1', [
            'url' => 'https://assets.example.test/ast_1.png',
            'filename' => $body['filename'] ?? null,
        ]));
    }],
    ['GET', '#^/v1/assets$#', static fn () => reply(200, cursorList())],
    ['DELETE', '#^/v1/assets/([^/]+)$#', static fn (array $m) => reply(200, entity('asset', $m[1], ['deleted' => true]))],

    // --- domains -----------------------------------------------------------
    ['POST', '#^/v1/domains$#', static fn () => reply(200, entity('domain', 'dom_1', ['records' => []]))],
    ['GET', '#^/v1/domains$#', static fn () => reply(200, cursorList())],
    ['POST', '#^/v1/domains/([^/]+)/verify$#', static fn (array $m) => reply(200, entity('domain', $m[1], ['status' => 'verified']))],
    ['POST', '#^/v1/domains/([^/]+)/tracking-domains$#', static function () use ($body): void {
        reply(200, entity('tracking_domain', 'trk_1', ['subdomain' => $body['subdomain'] ?? null, 'records' => []]));
    }],
    ['GET', '#^/v1/domains/([^/]+)/tracking-domains$#', static fn () => reply(200, listOf())],
    ['POST', '#^/v1/domains/([^/]+)/tracking-domains/([^/]+)/verify$#', static fn (array $m) => reply(200, entity('tracking_domain', $m[2], ['status' => 'verified']))],
    ['DELETE', '#^/v1/domains/([^/]+)/tracking-domains/([^/]+)$#', static fn (array $m) => reply(200, entity('tracking_domain', $m[2], ['deleted' => true]))],
    ['GET', '#^/v1/domains/([^/]+)$#', static fn (array $m) => reply(200, entity('domain', $m[1]))],
    ['PATCH', '#^/v1/domains/([^/]+)$#', static fn (array $m) => reply(200, entity('domain', $m[1]))],
    ['DELETE', '#^/v1/domains/([^/]+)$#', static fn (array $m) => reply(200, entity('domain', $m[1], ['deleted' => true]))],

    // --- webhooks ----------------------------------------------------------
    ['POST', '#^/v1/webhooks/endpoints$#', static fn () => reply(200, entity('webhook_endpoint', 'whe_1', ['signing_secret' => 'whsec_dGVzdA']))],
    ['GET', '#^/v1/webhooks/endpoints$#', static fn () => reply(200, listOf())],
    ['GET', '#^/v1/webhooks/endpoints/([^/]+)$#', static fn (array $m) => reply(200, entity('webhook_endpoint', $m[1]))],
    ['PATCH', '#^/v1/webhooks/endpoints/([^/]+)$#', static fn (array $m) => reply(200, entity('webhook_endpoint', $m[1]))],
    ['DELETE', '#^/v1/webhooks/endpoints/([^/]+)$#', static fn (array $m) => reply(200, entity('webhook_endpoint', $m[1], ['deleted' => true]))],

    // --- contact properties ------------------------------------------------
    ['POST', '#^/v1/contact-properties$#', static fn () => reply(200, entity('contact_property', 'cprop_1'))],
    ['GET', '#^/v1/contact-properties$#', static fn () => reply(200, listOf())],
    ['PATCH', '#^/v1/contact-properties/([^/]+)$#', static fn (array $m) => reply(200, entity('contact_property', $m[1]))],
    ['DELETE', '#^/v1/contact-properties/([^/]+)$#', static fn (array $m) => reply(200, entity('contact_property', $m[1], ['deleted' => true]))],

    // --- api keys ----------------------------------------------------------
    ['POST', '#^/v1/api-keys$#', static fn () => reply(200, ['object' => 'api_key', 'id' => 'key_1', 'token' => 'mt_pat_mock_token'])],
    ['GET', '#^/v1/api-keys$#', static fn () => reply(200, listOf())],
    // The real endpoint answers 200 with an EMPTY body here, which is exactly
    // the case the SDK has to turn into an empty array rather than a parse error.
    ['DELETE', '#^/v1/api-keys/([^/]+)$#', static fn () => reply(200, null)],

    // --- automations -------------------------------------------------------
    ['POST', '#^/v1/automations/validate$#', static fn () => reply(200, ['object' => 'automation_validation', 'valid' => true, 'issues' => []])],
    ['POST', '#^/v1/automations$#', static fn () => reply(200, entity('automation', 'auto_1', ['status' => 'draft']))],
    ['GET', '#^/v1/automations$#', static fn () => reply(200, cursorList())],
    ['POST', '#^/v1/automations/([^/]+)/activate$#', static fn (array $m) => reply(200, entity('automation', $m[1], ['status' => 'active']))],
    ['POST', '#^/v1/automations/([^/]+)/pause$#', static fn (array $m) => reply(200, entity('automation', $m[1], ['status' => 'paused', 'canceled_runs' => 0]))],
    ['POST', '#^/v1/automations/([^/]+)/archive$#', static fn (array $m) => reply(200, entity('automation', $m[1], ['status' => 'archived', 'canceled_runs' => 3]))],
    ['POST', '#^/v1/automations/([^/]+)/test$#', static fn () => reply(202, entity('automation_run', 'arun_1', ['is_test' => true]))],
    ['GET', '#^/v1/automations/([^/]+)/metrics$#', static fn () => reply(200, ['object' => 'automation_metrics', 'excludes_test_runs' => true, 'steps' => []])],
    ['GET', '#^/v1/automations/([^/]+)/versions$#', static fn () => reply(200, cursorList())],
    ['GET', '#^/v1/automations/([^/]+)/versions/([^/]+)$#', static fn (array $m) => reply(200, ['object' => 'automation_version', 'version' => (int) $m[2], 'steps' => [], 'connections' => []])],
    ['GET', '#^/v1/automations/([^/]+)/runs$#', static fn () => reply(200, cursorList())],
    ['GET', '#^/v1/automations/([^/]+)/runs/([^/]+)$#', static fn (array $m) => reply(200, entity('automation_run', $m[2], ['steps' => [], 'step_runs' => []]))],
    ['POST', '#^/v1/automations/([^/]+)/runs/([^/]+)/cancel$#', static fn (array $m) => reply(200, entity('automation_run', $m[2], ['status' => 'canceled']))],
    ['GET', '#^/v1/automations/([^/]+)$#', static fn (array $m) => reply(200, entity('automation', $m[1], ['steps' => [], 'issues' => []]))],
    ['PATCH', '#^/v1/automations/([^/]+)$#', static fn (array $m) => reply(200, entity('automation', $m[1]))],
    ['DELETE', '#^/v1/automations/([^/]+)$#', static fn (array $m) => reply(200, entity('automation', $m[1], ['deleted' => true]))],

    // --- events ------------------------------------------------------------
    ['POST', '#^/v1/events$#', static fn () => reply(202, ['object' => 'event', 'id' => 'evt_1', 'enrolled_automations' => 1, 'resumed_runs' => 0])],
    ['GET', '#^/v1/events$#', static fn () => reply(200, cursorList())],

    ['POST', '#^/v1/event-definitions$#', static fn () => reply(200, entity('event_definition', 'evdef_1'))],
    ['GET', '#^/v1/event-definitions$#', static fn () => reply(200, cursorList())],
    ['GET', '#^/v1/event-definitions/([^/]+)$#', static fn (array $m) => reply(200, entity('event_definition', $m[1], ['inferred_properties' => []]))],
    ['PATCH', '#^/v1/event-definitions/([^/]+)$#', static fn (array $m) => reply(200, entity('event_definition', $m[1]))],
    ['DELETE', '#^/v1/event-definitions/([^/]+)$#', static fn (array $m) => reply(200, entity('event_definition', $m[1], ['deleted' => true]))],
];

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    if ($routeMethod !== $method) {
        continue;
    }
    if (preg_match($pattern, $path, $matches) === 1) {
        $handler($matches);

        return;
    }
}

reply(404, ['error' => 'Not Found', 'path' => $path]);
