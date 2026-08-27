<?php

declare(strict_types=1);

/**
 * Posts and templates — the content half of the API.
 *
 * @var MockMailtea $server
 * @var Mailtea\Mailtea $mailtea
 */

use Mailtea\Request\CreatePost;
use Mailtea\Request\SendPost;
use Mailtea\Request\SendTestPost;

test('posts.create takes a typed payload', function () use ($mailtea, $server): void {
    $post = $mailtea->posts->create(new CreatePost(
        publicationId: 'pub_1',
        subject: 'Issue 12',
        html: '<p>Hello subscribers.</p>',
        kind: 'newsletter',
    ));

    assertSame('post_1', $post['id'], 'id');
    assertRequest($server, 'POST', '/v1/posts', [
        'publication_id' => 'pub_1',
        'subject' => 'Issue 12',
        'html' => '<p>Hello subscribers.</p>',
        'kind' => 'newsletter',
    ]);
});

test('posts.create can seed from a template', function () use ($mailtea, $server): void {
    $mailtea->posts->create(new CreatePost(
        publicationId: 'pub_1',
        subject: 'Issue 13',
        templateId: 'tpl_1',
        variables: ['name' => 'Ada'],
    ));

    $body = $server->last()['body'];
    // Posts take a flat `template_id`, not the nested `template` object a send
    // takes. Two endpoints, two shapes; the SDK is where that stops being the
    // caller's problem.
    assertSame('tpl_1', $body['template_id'], 'template_id');
    assertSame(['name' => 'Ada'], $body['variables'], 'variables');
});

test('posts.send with no schedule sends no body at all', function () use ($mailtea, $server): void {
    $mailtea->posts->send('post_1');

    assertRequest($server, 'POST', '/v1/posts/post_1/send');
    // An empty JSON object would be harmless here but a lie about intent; the
    // endpoint reads "no body" as "send it now".
    assertSame('', $server->last()['raw'], 'no request body');
});

test('posts.send with a schedule sends scheduled_at', function () use ($mailtea, $server): void {
    $mailtea->posts->send('post_1', new SendPost('2030-01-01T09:00:00.000Z'));
    assertRequest($server, 'POST', '/v1/posts/post_1/send', ['scheduled_at' => '2030-01-01T09:00:00.000Z']);
});

test('posts.sendTest goes to /test and returns who it reached', function () use ($mailtea, $server): void {
    $result = $mailtea->posts->sendTest('post_1', new SendTestPost(
        recipients: ['you@example.test'],
        from: 'Acme <hello@acme.test>',
    ));

    assertSame(['you@example.test'], $result['sent_to'], 'sent_to');
    assertRequest($server, 'POST', '/v1/posts/post_1/test', [
        'recipients' => ['you@example.test'],
        'from' => 'Acme <hello@acme.test>',
    ]);
});

test('posts list, get, update and delete', function () use ($mailtea, $server): void {
    $mailtea->posts->list(['publication_id' => 'pub_1', 'kind' => 'broadcast', 'limit' => 5]);
    assertRequest($server, 'GET', '/v1/posts', null, 'publication_id=pub_1&kind=broadcast&limit=5');

    $mailtea->posts->get('post_1');
    assertRequest($server, 'GET', '/v1/posts/post_1', null, '');

    $mailtea->posts->update('post_1', ['subject' => 'Issue 12, revised']);
    assertRequest($server, 'PATCH', '/v1/posts/post_1', ['subject' => 'Issue 12, revised']);

    $mailtea->posts->delete('post_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'DELETE', '/v1/posts/post_1', null, 'publication_id=pub_1');
});

test('templates.render does not create anything', function () use ($mailtea, $server): void {
    $rendered = $mailtea->templates->render(['spec' => ['blocks' => []], 'variables' => ['name' => 'Ada']]);

    assertSame('<p>Hi</p>', $rendered['html'], 'html');
    assertRequest($server, 'POST', '/v1/templates/render');
});

test('templates create, list, get and delete', function () use ($mailtea, $server): void {
    $mailtea->templates->create(['publication_id' => 'pub_1', 'name' => 'Receipt', 'html' => '<p>Hi</p>']);
    assertRequest($server, 'POST', '/v1/templates');

    $mailtea->templates->list(['publication_id' => 'pub_1', 'limit' => 20]);
    assertRequest($server, 'GET', '/v1/templates', null, 'publication_id=pub_1&limit=20');

    $mailtea->templates->get('tpl_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/templates/tpl_1', null, 'publication_id=pub_1');

    $mailtea->templates->delete('tpl_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'DELETE', '/v1/templates/tpl_1', null, 'publication_id=pub_1');
});

test('templates.update puts publication_id in the query and keeps nulls in the body', function () use ($mailtea, $server): void {
    $mailtea->templates->update('tpl_1', ['publication_id' => 'pub_1', 'subject' => null, 'name' => 'Receipt v2']);

    $request = $server->last();
    assertSame('publication_id=pub_1', $request['query'], 'query');
    assertTrue(array_key_exists('subject', $request['body']), 'a null subject reaches the body to clear it');
    assertSame(null, $request['body']['subject'], 'as a real null');
});

test('templates publish, unpublish and duplicate', function () use ($mailtea, $server): void {
    $published = $mailtea->templates->publish('tpl_1', ['publication_id' => 'pub_1']);
    assertSame('published', $published['status'], 'status');
    assertRequest($server, 'POST', '/v1/templates/tpl_1/publish', null, 'publication_id=pub_1');

    $mailtea->templates->unpublish('tpl_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'POST', '/v1/templates/tpl_1/unpublish', null, 'publication_id=pub_1');

    $copy = $mailtea->templates->duplicate('tpl_1', ['publication_id' => 'pub_1']);
    assertSame('tpl_copy', $copy['id'], 'the duplicate has a new id');
    assertRequest($server, 'POST', '/v1/templates/tpl_1/duplicate', null, 'publication_id=pub_1');
});

test('template versions list and restore', function () use ($mailtea, $server): void {
    $versions = $mailtea->templates->versions('tpl_1', ['publication_id' => 'pub_1', 'limit' => 10]);
    assertSame(true, $versions['data'][0]['is_current'], 'is_current');
    assertRequest($server, 'GET', '/v1/templates/tpl_1/versions', null, 'publication_id=pub_1&limit=10');

    $restored = $mailtea->templates->restoreVersion('tpl_1', 2, ['publication_id' => 'pub_1']);
    // Restoring is a content write, so the template drops back to draft. The
    // reply says so, and re-publishing is the caller's job.
    assertSame(true, $restored['unpublished'], 'unpublished');
    assertRequest($server, 'POST', '/v1/templates/tpl_1/versions/2/restore', null, 'publication_id=pub_1');
});
