<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * Tracking sub-domains (CNAME) under a sending domain — used to serve the open
 * pixel and click-tracking links from your own domain instead of a shared one.
 * Reach it at `$mailtea->domains->tracking`.
 */
final class TrackingDomains
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * Add a tracking sub-domain. Takes `publication_id` and `subdomain`. The
     * response `records` lists the CNAME to add.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function create(string $domainId, array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            '/v1/domains/' . Params::segment($domainId) . '/tracking-domains'
                . Params::query(['publication_id' => $params['publication_id'] ?? null]),
            ['subdomain' => $params['subdomain'] ?? null]
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(string $domainId, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'GET',
            '/v1/domains/' . Params::segment($domainId) . '/tracking-domains' . Params::query($params)
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function verify(string $domainId, string $trackingDomainId, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            '/v1/domains/' . Params::segment($domainId)
                . '/tracking-domains/' . Params::segment($trackingDomainId)
                . '/verify' . Params::query($params)
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function delete(string $domainId, string $trackingDomainId, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'DELETE',
            '/v1/domains/' . Params::segment($domainId)
                . '/tracking-domains/' . Params::segment($trackingDomainId)
                . Params::query($params)
        );
    }
}
