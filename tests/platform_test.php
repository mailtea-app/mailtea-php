<?php

declare(strict_types=1);

/**
 * Domains, tracking sub-domains, webhook endpoints and API keys.
 *
 * @var MockMailtea $server
 * @var Mailtea\Mailtea $mailtea
 */

test('domains create, list, get, verify, update and delete', function () use ($mailtea, $server): void {
    $domain = $mailtea->domains->create(['publication_id' => 'pub_1', 'domain' => 'acme.test']);
    assertTrue(array_key_exists('records', $domain), 'the DNS records to add come back on create');
    assertRequest($server, 'POST', '/v1/domains', ['publication_id' => 'pub_1', 'domain' => 'acme.test']);

    $mailtea->domains->list(['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/domains', null, 'publication_id=pub_1');

    $mailtea->domains->get('dom_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/domains/dom_1', null, 'publication_id=pub_1');

    $verified = $mailtea->domains->verify('dom_1', ['publication_id' => 'pub_1']);
    assertSame('verified', $verified['status'], 'status');
    assertRequest($server, 'POST', '/v1/domains/dom_1/verify', null, 'publication_id=pub_1');

    $mailtea->domains->update('dom_1', ['publication_id' => 'pub_1', 'custom_return_path' => 'bounces']);
    assertRequest(
        $server,
        'PATCH',
        '/v1/domains/dom_1',
        ['publication_id' => 'pub_1', 'custom_return_path' => 'bounces'],
        'publication_id=pub_1'
    );

    $mailtea->domains->delete('dom_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'DELETE', '/v1/domains/dom_1', null, 'publication_id=pub_1');
});

test('tracking domains nest under a domain and send only the subdomain', function () use ($mailtea, $server): void {
    $mailtea->domains->tracking->create('dom_1', ['publication_id' => 'pub_1', 'subdomain' => 'links']);
    // publication_id belongs in the query here; the body is the subdomain alone.
    assertRequest(
        $server,
        'POST',
        '/v1/domains/dom_1/tracking-domains',
        ['subdomain' => 'links'],
        'publication_id=pub_1'
    );

    $mailtea->domains->tracking->list('dom_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/domains/dom_1/tracking-domains', null, 'publication_id=pub_1');

    $mailtea->domains->tracking->verify('dom_1', 'trk_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'POST', '/v1/domains/dom_1/tracking-domains/trk_1/verify', null, 'publication_id=pub_1');

    $mailtea->domains->tracking->delete('dom_1', 'trk_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'DELETE', '/v1/domains/dom_1/tracking-domains/trk_1', null, 'publication_id=pub_1');
});

test('webhook endpoints live under /v1/webhooks/endpoints', function () use ($mailtea, $server): void {
    $endpoint = $mailtea->webhooks->create([
        'publication_id' => 'pub_1',
        'url' => 'https://acme.test/hooks/mailtea',
        'events' => ['email.delivered'],
    ]);
    // The signing secret is returned once, on create, and never again.
    assertTrue(is_string($endpoint['signing_secret']), 'signing_secret');
    assertRequest($server, 'POST', '/v1/webhooks/endpoints');

    $mailtea->webhooks->list(['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/webhooks/endpoints', null, 'publication_id=pub_1');

    $mailtea->webhooks->get('whe_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/webhooks/endpoints/whe_1', null, 'publication_id=pub_1');

    $mailtea->webhooks->update('whe_1', ['publication_id' => 'pub_1', 'enabled' => false]);
    assertRequest(
        $server,
        'PATCH',
        '/v1/webhooks/endpoints/whe_1',
        ['publication_id' => 'pub_1', 'enabled' => false],
        'publication_id=pub_1'
    );

    $mailtea->webhooks->delete('whe_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'DELETE', '/v1/webhooks/endpoints/whe_1', null, 'publication_id=pub_1');
});

test('api keys create and list', function () use ($mailtea, $server): void {
    $key = $mailtea->apiKeys->create(['name' => 'CI', 'permission' => 'sending_access']);
    assertTrue(is_string($key['token']), 'the token is returned once, on create');
    assertRequest($server, 'POST', '/v1/api-keys', ['name' => 'CI', 'permission' => 'sending_access']);

    $mailtea->apiKeys->list();
    assertRequest($server, 'GET', '/v1/api-keys', null, '');
});
