<?php

declare(strict_types=1);

/**
 * Domain claims — take a domain back from the team that currently holds it.
 *
 * @var MockMailtea $server
 * @var Mailtea\Mailtea $mailtea
 */

test('claims open, poll, verify and cancel', function () use ($mailtea, $server): void {
    $claim = $mailtea->domains->claims->create([
        'publication_id' => 'pub_1',
        'name' => 'acme.com',
        'region' => 'eu-west-1',
    ]);
    assertSame('pending', $claim['status'], 'a new claim is pending');
    assertSame('Claim', $claim['records'][0]['record'], 'the TXT record to publish comes back on create');
    assertRequest($server, 'POST', '/v1/domains/claim', [
        'publication_id' => 'pub_1',
        'name' => 'acme.com',
        'region' => 'eu-west-1',
    ]);

    $mailtea->domains->claims->get('clm_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/domains/claims/clm_1', null, 'publication_id=pub_1');

    $verified = $mailtea->domains->claims->verify('clm_1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'POST', '/v1/domains/claims/clm_1/verify', null, 'publication_id=pub_1');
    // A completed claim answers with the fresh domain beside it, so the
    // claimant can publish its DNS without a second request.
    assertSame('dom_2', $verified['domain']['id'], 'the claimed domain comes back on verify');
    assertSame('dom_2', $verified['domain_id'], 'domain_id names the same row');

    $canceled = $mailtea->domains->claims->cancel('clm_1', ['publication_id' => 'pub_1']);
    assertSame(true, $canceled['deleted'], 'a withdrawn claim reports deleted');
    assertRequest($server, 'DELETE', '/v1/domains/claims/clm_1', null, 'publication_id=pub_1');
});

test('the claim id is escaped into its path segment', function () use ($mailtea, $server): void {
    $mailtea->domains->claims->get('clm/1', ['publication_id' => 'pub_1']);
    assertRequest($server, 'GET', '/v1/domains/claims/clm%2F1', null, 'publication_id=pub_1');
});

// Pass-through: the multi-region fields need no code, and this is what fails if
// that ever stops being true.
test('domains forward region, tls and tracking_subdomain untouched', function () use ($mailtea, $server): void {
    $mailtea->domains->create([
        'publication_id' => 'pub_1',
        'name' => 'acme.com',
        'region' => 'ap-southeast-1',
        'tls' => 'enforced',
        'tracking_subdomain' => 'links',
    ]);
    assertRequest($server, 'POST', '/v1/domains', [
        'publication_id' => 'pub_1',
        'name' => 'acme.com',
        'region' => 'ap-southeast-1',
        'tls' => 'enforced',
        'tracking_subdomain' => 'links',
    ]);

    $mailtea->domains->update('dom_1', [
        'publication_id' => 'pub_1',
        'tls' => 'enforced',
        'tracking_subdomain' => 'links',
    ]);
    assertRequest($server, 'PATCH', '/v1/domains/dom_1', [
        'publication_id' => 'pub_1',
        'tls' => 'enforced',
        'tracking_subdomain' => 'links',
    ], 'publication_id=pub_1');

    $mailtea->domains->list([
        'publication_id' => 'pub_1',
        'region' => 'eu-west-1',
        'status' => 'verified',
    ]);
    assertRequest(
        $server,
        'GET',
        '/v1/domains',
        null,
        'publication_id=pub_1&region=eu-west-1&status=verified'
    );
});

// The removal has to reach the wire AS null. The query builder drops nulls; the
// body must not, or "remove it" becomes "leave it alone" and the caller gets a
// 200 saying nothing happened.
test('domains update sends an explicit null to clear the tracking subdomain', function () use ($mailtea, $server): void {
    $mailtea->domains->update('dom_1', [
        'publication_id' => 'pub_1',
        'tracking_subdomain' => null,
    ]);
    assertRequest($server, 'PATCH', '/v1/domains/dom_1', [
        'publication_id' => 'pub_1',
        'tracking_subdomain' => null,
    ], 'publication_id=pub_1');

    $request = $server->last();
    assertTrue(
        array_key_exists('tracking_subdomain', $request['body']),
        'the key must be present, not merely null-ish'
    );
});

// Three states, not two: an absent key leaves the subdomain alone, null removes
// it. A body that always carried the key would clear it on every update.
test('domains update omits the tracking subdomain when it is not named', function () use ($mailtea, $server): void {
    $mailtea->domains->update('dom_1', [
        'publication_id' => 'pub_1',
        'tls' => 'enforced',
    ]);
    $request = $server->last();
    assertTrue(
        !array_key_exists('tracking_subdomain', $request['body']),
        'an update that never named the subdomain must not carry the key'
    );
});
