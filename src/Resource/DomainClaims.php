<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * Domain claims. Reach it at `$mailtea->domains->claims`.
 *
 * Use this when adding a domain is refused because the host is connected to
 * another publication: open a claim, publish the TXT record the response lists
 * to prove you control the DNS, then {@see self::verify()}. On success the
 * other team's domain is released and a fresh one is created for you.
 */
final class DomainClaims
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * Open a claim. Takes `publication_id`, `name` and an optional `region`.
     * The response `records` lists the TXT record to publish.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/domains/claim', $params);
    }

    /**
     * Poll a claim. Requires `publication_id`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function get(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'GET',
            '/v1/domains/claims/' . Params::segment($id) . Params::query($params)
        );
    }

    /**
     * Check the TXT record and complete the claim if it is there.
     *
     * Safe to call repeatedly: a record that has not propagated yet leaves the
     * claim pending with the same record, so nothing has to be republished. A
     * completed claim answers with the fresh `domain` beside the claim.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function verify(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            '/v1/domains/claims/' . Params::segment($id) . '/verify' . Params::query($params)
        );
    }

    /**
     * Withdraw a pending claim. Requires `publication_id`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function cancel(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'DELETE',
            '/v1/domains/claims/' . Params::segment($id) . Params::query($params)
        );
    }
}
