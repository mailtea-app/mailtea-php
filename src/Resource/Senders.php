<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * Named From identities. Reach it at `$mailtea->senders`.
 *
 * Scoped to a publication — every call carries a `publication_id`. `create`
 * takes `name` and `email` (the address must live on a verified, DKIM-verified
 * email domain), plus optional `reply_to` and `is_default`. The `email` is
 * immutable, so {@see self::update()} only changes `name`, `reply_to` and
 * `is_default`.
 */
final class Senders
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/senders', $params);
    }

    /**
     * List senders, cursor-paginated. Filters: `publication_id` (required),
     * `limit`, `after` (a cursor from a previous `next_cursor`).
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/senders' . Params::query($params));
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
            '/v1/senders/' . Params::segment($id) . Params::query($params)
        );
    }

    /**
     * Update a sender. `publication_id` is required in the body.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('PATCH', '/v1/senders/' . Params::segment($id), $params);
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
            '/v1/senders/' . Params::segment($id) . Params::query($params)
        );
    }
}
