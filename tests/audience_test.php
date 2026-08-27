<?php

declare(strict_types=1);

/**
 * Contacts, segments, topics, senders, custom properties, suppressions and the
 * asset library — everything scoped to an audience rather than a send.
 *
 * @var MockMailtea $server
 * @var Mailtea\Mailtea $mailtea
 */

use Mailtea\Request\CreateContact;
use Mailtea\Request\CreateTopic;
use Mailtea\Request\UpdateContact;

test('contacts.create posts a typed payload', function () use ($mailtea, $server): void {
    $contact = $mailtea->contacts->create(new CreateContact(
        publicationId: 'pub_1',
        email: 'reader@example.test',
    ));

    assertSame('con_1', $contact['id'], 'id');
    assertRequest($server, 'POST', '/v1/contacts', [
        'publication_id' => 'pub_1',
        'email' => 'reader@example.test',
    ]);
});

test('contacts.upsert is the same call as create', function () use ($mailtea, $server): void {
    $mailtea->contacts->upsert(['publication_id' => 'pub_1', 'email' => 'again@example.test']);
    assertRequest($server, 'POST', '/v1/contacts');
});

test('contacts.get takes an email address as well as an id', function () use ($mailtea, $server): void {
    $mailtea->contacts->get('reader@example.test', ['publication_id' => 'pub_1']);
    // The @ and the dots survive; nothing else has to.
    assertRequest($server, 'GET', '/v1/contacts/reader%40example.test', null, 'publication_id=pub_1');
});

test('contacts.update sends publication_id in both the query and the body', function () use ($mailtea, $server): void {
    $mailtea->contacts->update('con_1', new UpdateContact(publicationId: 'pub_1', status: 'unsubscribed'));

    assertRequest(
        $server,
        'PATCH',
        '/v1/contacts/con_1',
        ['publication_id' => 'pub_1', 'status' => 'unsubscribed'],
        'publication_id=pub_1'
    );
});

test('contacts.list and delete pass their filters through', function () use ($mailtea, $server): void {
    $mailtea->contacts->list(['publication_id' => 'pub_1', 'status' => 'active', 'limit' => 50]);
    assertRequest($server, 'GET', '/v1/contacts', null, 'publication_id=pub_1&status=active&limit=50');

    $mailtea->contacts->delete('con_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'DELETE', '/v1/contacts/con_1', null, 'publication_id=pub_1');
});

test('segments cover the whole CRUD', function () use ($mailtea, $server): void {
    $mailtea->segments->create(['publication_id' => 'pub_1', 'name' => 'Engaged']);
    assertRequest($server, 'POST', '/v1/segments', ['publication_id' => 'pub_1', 'name' => 'Engaged']);

    $mailtea->segments->list(['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/segments', null, 'publication_id=pub_1');

    $mailtea->segments->get('seg_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/segments/seg_1', null, 'publication_id=pub_1');

    // A null value is a deliberate "clear this filter", so it must reach the
    // body — unlike a null in a QUERY string, which is dropped.
    $mailtea->segments->update('seg_1', ['publication_id' => 'pub_1', 'status_filter' => null]);
    $request = $server->last();
    assertSame('PATCH', $request['method'], 'method');
    assertSame('publication_id=pub_1', $request['query'], 'query');
    assertTrue(array_key_exists('status_filter', $request['body']), 'the null reaches the body');
    assertSame(null, $request['body']['status_filter'], 'as a real null');

    $mailtea->segments->delete('seg_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'DELETE', '/v1/segments/seg_1', null, 'publication_id=pub_1');
});

test('topics.create takes a typed payload with the required default_subscription', function () use ($mailtea, $server): void {
    $topic = $mailtea->topics->create(new CreateTopic(
        publicationId: 'pub_1',
        name: 'Weekly digest',
        defaultSubscription: 'opt_in',
        visibility: 'public',
    ));

    assertSame('Weekly digest', $topic['name'], 'name');
    assertRequest($server, 'POST', '/v1/topics', [
        'publication_id' => 'pub_1',
        'name' => 'Weekly digest',
        'default_subscription' => 'opt_in',
        'visibility' => 'public',
    ]);
});

test('topics list, get, update and delete', function () use ($mailtea, $server): void {
    $mailtea->topics->list(['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/topics', null, 'publication_id=pub_1');

    $mailtea->topics->get('top_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/topics/top_1', null, 'publication_id=pub_1');

    $mailtea->topics->update('top_1', ['publication_id' => 'pub_1', 'name' => 'Renamed']);
    assertRequest($server, 'PATCH', '/v1/topics/top_1', ['publication_id' => 'pub_1', 'name' => 'Renamed'], 'publication_id=pub_1');

    $mailtea->topics->delete('top_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'DELETE', '/v1/topics/top_1', null, 'publication_id=pub_1');
});

test('senders cover the whole CRUD, and update keeps publication_id in the body', function () use ($mailtea, $server): void {
    $mailtea->senders->create(['publication_id' => 'pub_1', 'name' => 'Acme', 'email' => 'hi@acme.test']);
    assertRequest($server, 'POST', '/v1/senders');

    $mailtea->senders->list(['publication_id' => 'pub_1', 'limit' => 10]);
    assertRequest($server, 'GET', '/v1/senders', null, 'publication_id=pub_1&limit=10');

    $mailtea->senders->get('snd_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/senders/snd_1', null, 'publication_id=pub_1');

    // This one is body-only — no publication_id in the query, unlike contacts.
    $mailtea->senders->update('snd_1', ['publication_id' => 'pub_1', 'is_default' => true]);
    assertRequest($server, 'PATCH', '/v1/senders/snd_1', ['publication_id' => 'pub_1', 'is_default' => true], '');

    $mailtea->senders->delete('snd_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'DELETE', '/v1/senders/snd_1', null, 'publication_id=pub_1');
});

test('contact properties are team-scoped, with no publication_id', function () use ($mailtea, $server): void {
    $mailtea->contactProperties->create(['key' => 'plan', 'type' => 'string']);
    assertRequest($server, 'POST', '/v1/contact-properties', ['key' => 'plan', 'type' => 'string']);

    $mailtea->contactProperties->list();
    assertRequest($server, 'GET', '/v1/contact-properties', null, '');

    $mailtea->contactProperties->update('cprop_1', ['key' => 'tier']);
    assertRequest($server, 'PATCH', '/v1/contact-properties/cprop_1', ['key' => 'tier']);

    $mailtea->contactProperties->delete('cprop_1');
    assertRequest($server, 'DELETE', '/v1/contact-properties/cprop_1', null, '');
});

test('suppressions add, remove and list', function () use ($mailtea, $server): void {
    $added = $mailtea->suppressions->add(['emails' => ['a@example.test', 'b@example.test'], 'reason' => 'manual']);
    assertSame(2, $added['added'], 'added count');
    assertRequest($server, 'POST', '/v1/suppressions');

    // A DELETE that carries a body — unusual, and exactly what this endpoint wants.
    $removed = $mailtea->suppressions->remove(['emails' => ['a@example.test']]);
    assertSame(1, $removed['removed'], 'removed count');
    assertRequest($server, 'DELETE', '/v1/suppressions', ['emails' => ['a@example.test']]);

    $mailtea->suppressions->list(['reason' => 'bounce', 'limit' => 10]);
    assertRequest($server, 'GET', '/v1/suppressions', null, 'reason=bounce&limit=10');
});

test('suppressions.export returns raw CSV, not an array', function () use ($mailtea, $server): void {
    $csv = $mailtea->suppressions->export();

    assertTrue(is_string($csv), 'a string comes back');
    assertContainsString('email,reason,source,created_at', $csv, 'the CSV header row');
    assertRequest($server, 'GET', '/v1/suppressions/export');
});

test('assets upload, list and delete', function () use ($mailtea, $server): void {
    $asset = $mailtea->assets->upload([
        'publication_id' => 'pub_1',
        'content' => base64_encode('not-really-a-png'),
        'content_type' => 'image/png',
        'filename' => 'hero.png',
    ]);

    assertSame('https://assets.example.test/ast_1.png', $asset['url'], 'the URL to put in an image block');
    assertRequest($server, 'POST', '/v1/assets');
    assertSame(base64_encode('not-really-a-png'), $server->last()['body']['content'], 'content is sent as base64');

    $mailtea->assets->list(['publication_id' => 'pub_1', 'limit' => 100]);
    assertRequest($server, 'GET', '/v1/assets', null, 'publication_id=pub_1&limit=100');

    $mailtea->assets->delete('ast_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'DELETE', '/v1/assets/ast_1', null, 'publication_id=pub_1');
});

test('assets.uploadFile reads the file and base64-encodes it', function () use ($mailtea, $server): void {
    $path = (string) tempnam(sys_get_temp_dir(), 'mailtea-asset-') . '.png';
    file_put_contents($path, "\x89PNG\r\n\x1a\nnot-a-real-png");

    try {
        $mailtea->assets->uploadFile($path, ['publication_id' => 'pub_1', 'content_type' => 'image/png']);

        $body = $server->last()['body'];
        assertSame(base64_encode((string) file_get_contents($path)), $body['content'], 'encoded content');
        assertSame(basename($path), $body['filename'], 'filename inferred from the path');
    } finally {
        @unlink($path);
    }
});

test('assets.uploadFile reports an unreadable file as a MailteaException', function () use ($mailtea): void {
    $error = assertThrows(static fn () => $mailtea->assets->uploadFile(
        '/nonexistent/hero.png',
        ['publication_id' => 'pub_1', 'content_type' => 'image/png']
    ));

    assertSame('asset_file_unreadable', $error->errorCode, 'error code');
    assertSame(0, $error->status, 'status');
});
