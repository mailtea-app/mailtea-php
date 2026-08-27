<?php

declare(strict_types=1);

/**
 * @var MockMailtea $server
 * @var Mailtea\Mailtea $mailtea
 */

use Mailtea\Request\BatchEmail;
use Mailtea\Request\SendEmail;
use Mailtea\Request\UpdateEmail;

test('send posts a typed payload to /v1/emails', function () use ($mailtea, $server): void {
    $sent = $mailtea->emails->send(new SendEmail(
        from: 'Acme <hello@acme.test>',
        to: 'reader@example.test',
        subject: 'Hello from php',
        html: '<p>Sent with PHP.</p>',
        text: 'Sent with PHP.',
        tags: [['name' => 'example', 'value' => 'php']],
    ));

    assertSame(MOCK_EMAIL_ID, $sent['id'], 'returned id');
    assertRequest($server, 'POST', '/v1/emails', [
        'from' => 'Acme <hello@acme.test>',
        'to' => 'reader@example.test',
        'subject' => 'Hello from php',
        'html' => '<p>Sent with PHP.</p>',
        'text' => 'Sent with PHP.',
        'tags' => [['name' => 'example', 'value' => 'php']],
    ]);
});

test('send takes a plain wire-format array too', function () use ($mailtea, $server): void {
    $mailtea->emails->send([
        'from' => 'Acme <hello@acme.test>',
        'to' => ['a@example.test', 'b@example.test'],
        'subject' => 'Array form',
        'html' => '<p>Hi</p>',
        // A field the typed payload does not model still goes out untouched.
        'idempotency_key' => 'key-123',
    ]);

    $body = $server->last()['body'];
    assertSame(['a@example.test', 'b@example.test'], $body['to'], 'to');
    assertSame('key-123', $body['idempotency_key'], 'an unmodelled field survives');
});

test('a typed payload omits what was never set and nests the template', function () use ($mailtea, $server): void {
    $mailtea->emails->send(new SendEmail(
        senderId: 'snd_1',
        to: 'reader@example.test',
        subject: 'Templated',
        templateId: 'tpl_1',
        variables: ['name' => 'Ada'],
        trackingClick: false,
    ));

    $body = $server->last()['body'];
    assertSame(['id' => 'tpl_1', 'variables' => ['name' => 'Ada']], $body['template'], 'template');
    assertSame('snd_1', $body['sender_id'], 'sender_id');
    assertSame(false, $body['tracking_click'], 'tracking_click stays a real boolean');
    // Not "from": null — the API refuses a payload carrying both `from` and
    // `sender_id`, and a null counts.
    assertTrue(!array_key_exists('from', $body), 'from is absent, not null');
    assertTrue(!array_key_exists('tracking_open', $body), 'unset fields are absent');
});

test('batch sends a bare JSON array', function () use ($mailtea, $server): void {
    $result = $mailtea->emails->batch([
        new BatchEmail(from: 'a@acme.test', to: 'x@example.test', subject: '1', html: '<p>1</p>'),
        ['from' => 'a@acme.test', 'to' => 'y@example.test', 'subject' => '2', 'html' => '<p>2</p>'],
    ]);

    assertSame(2, count($result['data']), 'two ids back');
    $request = $server->last();
    assertSame('/v1/emails/batch', $request['path'], 'path');
    assertTrue(array_is_list($request['body']), 'the body is a JSON array, not an object');
    assertSame('1', $request['body'][0]['subject'], 'first item');
    assertSame('2', $request['body'][1]['subject'], 'second item');
});

test('get reads the email and aliases last_event to status', function () use ($mailtea, $server): void {
    $email = $mailtea->emails->get(MOCK_EMAIL_ID);

    assertSame('delivered', $email['last_event'], 'last_event');
    assertSame('delivered', $email['status'], 'status alias');
    assertRequest($server, 'GET', '/v1/emails/' . MOCK_EMAIL_ID);
});

test('an id is escaped into its path segment', function () use ($mailtea, $server): void {
    $mailtea->emails->get('has space');
    assertSame('/v1/emails/has%20space', $server->last()['path'], 'escaped path');
});

test('list builds a query string and drops nulls', function () use ($mailtea, $server): void {
    $mailtea->emails->list(['status' => 'sent', 'limit' => 10, 'search' => 'invoice', 'offset' => null]);

    $query = $server->last()['query'];
    assertContainsString('status=sent', $query, 'status');
    assertContainsString('limit=10', $query, 'limit');
    assertContainsString('search=invoice', $query, 'search');
    assertTrue(!str_contains($query, 'offset'), 'a null filter is dropped entirely');
    assertSame('/v1/emails', $server->last()['path'], 'path');
});

test('analytics has its own path, not an id lookup', function () use ($mailtea, $server): void {
    $analytics = $mailtea->emails->analytics(['from_date' => '2026-08-01T00:00:00Z']);

    assertSame('email_analytics', $analytics['object'], 'object');
    assertRequest($server, 'GET', '/v1/emails/analytics', null, 'from_date=2026-08-01T00%3A00%3A00Z');
});

test('update and reschedule patch scheduled_at', function () use ($mailtea, $server): void {
    $mailtea->emails->update(MOCK_EMAIL_ID, new UpdateEmail('2030-06-01T12:00:00.000Z'));
    assertRequest($server, 'PATCH', '/v1/emails/' . MOCK_EMAIL_ID, ['scheduled_at' => '2030-06-01T12:00:00.000Z']);

    $mailtea->emails->reschedule(MOCK_EMAIL_ID, '2030-07-01T12:00:00.000Z');
    assertRequest($server, 'PATCH', '/v1/emails/' . MOCK_EMAIL_ID, ['scheduled_at' => '2030-07-01T12:00:00.000Z']);
});

test('cancel POSTs to /cancel — there is no DELETE on emails', function () use ($mailtea, $server): void {
    $canceled = $mailtea->emails->cancel(MOCK_EMAIL_ID);

    assertSame('email', $canceled['object'], 'object');
    assertSame(MOCK_EMAIL_ID, $canceled['id'], 'id');
    // The real endpoint returns the object and id and nothing else. Asserting the
    // absence keeps the mock from drifting into inventing fields.
    assertTrue(!array_key_exists('last_event', $canceled), 'no invented last_event');
    assertRequest($server, 'POST', '/v1/emails/' . MOCK_EMAIL_ID . '/cancel');
    assertSame(null, $server->last()['body'], 'no body is sent');
});

// --- inbound ---------------------------------------------------------------

test('inbound.list is cursor-paginated under /v1/emails/inbound', function () use ($mailtea, $server): void {
    $mailtea->emails->inbound->list(['publication_id' => 'pub_1', 'limit' => 5]);
    assertRequest($server, 'GET', '/v1/emails/inbound', null, 'publication_id=pub_1&limit=5');
});

test('inbound.get retrieves one received email', function () use ($mailtea, $server): void {
    $email = $mailtea->emails->inbound->get('inb_1');
    assertSame('inbound_email', $email['object'], 'object');
    assertRequest($server, 'GET', '/v1/emails/inbound/inb_1');
});

test('inbound.reply posts only the content', function () use ($mailtea, $server): void {
    $reply = $mailtea->emails->inbound->reply('inb_1', ['html' => '<p>Thanks!</p>']);
    assertSame(MOCK_EMAIL_ID, $reply['id'], 'the resulting email id');
    assertRequest($server, 'POST', '/v1/emails/inbound/inb_1/reply', ['html' => '<p>Thanks!</p>']);
});

test('inbound attachments list and get carry signed URLs', function () use ($mailtea, $server): void {
    $list = $mailtea->emails->inbound->attachments->list('inb_1');
    assertSame('https://example.test/a', $list['data'][0]['download_url'], 'download url');
    assertRequest($server, 'GET', '/v1/emails/inbound/inb_1/attachments');

    $one = $mailtea->emails->inbound->attachments->get('inb_1', 'inatt_1');
    assertSame('inatt_1', $one['id'], 'attachment id');
    assertRequest($server, 'GET', '/v1/emails/inbound/inb_1/attachments/inatt_1');
});

test('an empty batch fails locally instead of round-tripping a malformed body', function () use ($mailtea, $server): void {
    $before = count($server->requests());
    $error = assertThrows(static fn () => $mailtea->emails->batch([]));

    assertSame('empty_batch', $error->errorCode, 'error code');
    assertSame(0, $error->status, 'status');
    assertSame($before, count($server->requests()), 'no request was made');
});
