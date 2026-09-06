<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * Sending domains. Reach it at `$mailtea->domains`.
 *
 * Scoped to a publication — every call carries a `publication_id`. Register a
 * domain, add the DNS `records` the response lists, then {@see self::verify()}
 * it before sending from it.
 *
 * {@see self::create()} takes `region` (fixed at creation), `tls` and
 * `tracking_subdomain`; {@see self::list()} filters on `region` and `status`.
 */
final class Domains
{
    /** Tracking sub-domains (CNAME) under a domain. */
    public readonly TrackingDomains $tracking;

    /** Domain claims — take a domain back from another publication. */
    public readonly DomainClaims $claims;

    public function __construct(private readonly Requester $api)
    {
        $this->tracking = new TrackingDomains($api);
        $this->claims = new DomainClaims($api);
    }

    /**
     * Register a domain. The response `records` lists the DNS records to add.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/domains', $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/domains' . Params::query($params));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function get(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'GET',
            '/v1/domains/' . Params::segment($id) . Params::query($params)
        );
    }

    /**
     * Verify a domain against its DNS records; `status` becomes `verified`.
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
            '/v1/domains/' . Params::segment($id) . '/verify' . Params::query($params)
        );
    }

    /**
     * Update a domain — tracking settings, or `custom_return_path` to delegate a
     * subdomain as the envelope sender so SPF aligns with your own domain. Mail
     * keeps sending on the default return-path until the delegated DNS resolves.
     *
     * `'tracking_subdomain' => null` removes a tracking subdomain: the domain's
     * links go back to being served from the Mailtea host, and links in mail
     * already sent point at the old hostname and stop resolving. The params
     * array is encoded as given, so the null reaches the wire as a JSON null —
     * leaving the key out (leave the subdomain alone) and passing null (remove
     * it) are different requests. An empty string is neither; it is refused
     * with `tracking_subdomain_invalid`. null is an update-only value: a create
     * has nothing to clear.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'PATCH',
            '/v1/domains/' . Params::segment($id)
                . Params::query(['publication_id' => $params['publication_id'] ?? null]),
            $params
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function delete(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'DELETE',
            '/v1/domains/' . Params::segment($id) . Params::query($params)
        );
    }
}
