<?php

declare(strict_types=1);

/**
 * Automations, their runs, and the events that drive them.
 *
 * @var MockMailtea $server
 * @var Mailtea\Mailtea $mailtea
 */

test('automations.validate dry-runs a graph without creating one', function () use ($mailtea, $server): void {
    $result = $mailtea->automations->validate(['publication_id' => 'pub_1', 'steps' => []]);

    assertSame(true, $result['valid'], 'valid');
    assertRequest($server, 'POST', '/v1/automations/validate', ['publication_id' => 'pub_1', 'steps' => []]);
});

test('automations create, list, get and delete', function () use ($mailtea, $server): void {
    $automation = $mailtea->automations->create([
        'publication_id' => 'pub_1',
        'name' => 'Welcome',
        'steps' => [['key' => 'trigger', 'type' => 'trigger_contact_created']],
    ]);
    assertSame('draft', $automation['status'], 'new automations start as drafts');
    assertRequest($server, 'POST', '/v1/automations');

    $mailtea->automations->list(['publication_id' => 'pub_1', 'status' => 'active']);
    assertRequest($server, 'GET', '/v1/automations', null, 'publication_id=pub_1&status=active');

    $mailtea->automations->get('auto_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/automations/auto_1', null, 'publication_id=pub_1');

    $mailtea->automations->delete('auto_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'DELETE', '/v1/automations/auto_1', null, 'publication_id=pub_1');
});

test('automations.update moves publication_id OUT of the body into the query', function () use ($mailtea, $server): void {
    $mailtea->automations->update('auto_1', ['publication_id' => 'pub_1', 'name' => 'Welcome v2']);

    $request = $server->last();
    assertSame('publication_id=pub_1', $request['query'], 'query');
    assertSame(['name' => 'Welcome v2'], $request['body'], 'the body carries no publication_id');
});

test('activate, pause and archive carry their own cancel_runs defaults', function () use ($mailtea, $server): void {
    $mailtea->automations->activate('auto_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'POST', '/v1/automations/auto_1/activate', null, 'publication_id=pub_1');

    // No cancel_runs given: send NO body, so the endpoint's own default applies
    // (false for pause, true for archive). An explicit null would be rejected by
    // the server's schema.
    $mailtea->automations->pause('auto_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'POST', '/v1/automations/auto_1/pause', null, 'publication_id=pub_1');
    assertSame('', $server->last()['raw'], 'no body when cancel_runs is omitted');

    $mailtea->automations->pause('auto_1', ['publication_id' => 'pub_1', 'cancel_runs' => true]);
    assertRequest($server, 'POST', '/v1/automations/auto_1/pause', ['cancel_runs' => true], 'publication_id=pub_1');

    $archived = $mailtea->automations->archive('auto_1', ['publication_id' => 'pub_1']);
    assertSame(3, $archived['canceled_runs'], 'canceled_runs');
    assertRequest($server, 'POST', '/v1/automations/auto_1/archive', null, 'publication_id=pub_1');
});

test('automation versions and metrics', function () use ($mailtea, $server): void {
    $mailtea->automations->versions('auto_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/automations/auto_1/versions', null, 'publication_id=pub_1');

    $version = $mailtea->automations->version('auto_1', 3, ['publication_id' => 'pub_1']);
    assertSame(3, $version['version'], 'the pinned version');
    assertRequest($server, 'GET', '/v1/automations/auto_1/versions/3', null, 'publication_id=pub_1');

    $metrics = $mailtea->automations->metrics('auto_1', ['publication_id' => 'pub_1', 'version' => 3]);
    assertSame(true, $metrics['excludes_test_runs'], 'test runs are always excluded');
    assertRequest($server, 'GET', '/v1/automations/auto_1/metrics', null, 'publication_id=pub_1&version=3');
});

test('automations.test splits publication_id from the body', function () use ($mailtea, $server): void {
    $run = $mailtea->automations->test('auto_1', [
        'publication_id' => 'pub_1',
        'email' => 'you@example.test',
        'event_properties' => ['plan' => 'pro'],
    ]);

    assertSame(true, $run['is_test'], 'is_test');
    assertRequest(
        $server,
        'POST',
        '/v1/automations/auto_1/test',
        ['email' => 'you@example.test', 'event_properties' => ['plan' => 'pro']],
        'publication_id=pub_1'
    );
});

test('automation runs list, get and cancel', function () use ($mailtea, $server): void {
    // A list of statuses is comma-joined, and a bool goes out as the literal
    // string the server matches — `is_test=1` is a 400.
    $mailtea->automationRuns->list('auto_1', [
        'publication_id' => 'pub_1',
        'status' => ['running', 'waiting'],
        'is_test' => false,
    ]);
    assertRequest(
        $server,
        'GET',
        '/v1/automations/auto_1/runs',
        null,
        'publication_id=pub_1&status=running%2Cwaiting&is_test=false'
    );

    $mailtea->automationRuns->get('auto_1', 'arun_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/automations/auto_1/runs/arun_1', null, 'publication_id=pub_1');

    $canceled = $mailtea->automationRuns->cancel('auto_1', 'arun_1', ['publication_id' => 'pub_1']);
    assertSame('canceled', $canceled['status'], 'status');
    assertRequest($server, 'POST', '/v1/automations/auto_1/runs/arun_1/cancel', null, 'publication_id=pub_1');
});

test('events.send records an event and reports what it triggered', function () use ($mailtea, $server): void {
    $event = $mailtea->events->send([
        'publication_id' => 'pub_1',
        'name' => 'order.completed',
        'email' => 'buyer@example.test',
        'properties' => ['total' => 42],
    ]);

    assertSame(1, $event['enrolled_automations'], 'enrolled_automations');
    assertRequest($server, 'POST', '/v1/events');

    $mailtea->events->list(['publication_id' => 'pub_1', 'name' => 'order.completed']);
    assertRequest($server, 'GET', '/v1/events', null, 'publication_id=pub_1&name=order.completed');
});

test('event definitions cover the whole CRUD', function () use ($mailtea, $server): void {
    $mailtea->eventDefinitions->create(['publication_id' => 'pub_1', 'name' => 'order.completed']);
    assertRequest($server, 'POST', '/v1/event-definitions');

    $mailtea->eventDefinitions->list(['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/event-definitions', null, 'publication_id=pub_1');

    $definition = $mailtea->eventDefinitions->get('evdef_1', ['publication_id' => 'pub_1']);
    assertTrue(array_key_exists('inferred_properties', $definition), 'get adds inferred_properties');
    assertRequest($server, 'GET', '/v1/event-definitions/evdef_1', null, 'publication_id=pub_1');

    // Like automations.update, publication_id moves to the query.
    $mailtea->eventDefinitions->update('evdef_1', ['publication_id' => 'pub_1', 'schema_json' => null]);
    $request = $server->last();
    assertSame('publication_id=pub_1', $request['query'], 'query');
    assertSame(['schema_json' => null], $request['body'], 'a null clears the schema');

    $mailtea->eventDefinitions->delete('evdef_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'DELETE', '/v1/event-definitions/evdef_1', null, 'publication_id=pub_1');
});
