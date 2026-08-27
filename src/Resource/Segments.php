<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * Audience segments. Reach it at `$mailtea->segments`.
 *
 * Scoped to a publication — every call carries a `publication_id`. To clear a
 * nullable filter on update, pass it as null (`['status_filter' => null]`);
 * leave the key out entirely to keep the current value. That distinction is why
 * these take plain arrays rather than a typed payload.
 */
final class Segments
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
        return $this->api->request('POST', '/v1/segments', $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/segments' . Params::query($params));
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
            '/v1/segments/' . Params::segment($id) . Params::query($params)
        );
    }

    /**
     * Update a segment. `publication_id` goes in both the query string and the
     * body, the way the route reads it.
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
            '/v1/segments/' . Params::segment($id)
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
            '/v1/segments/' . Params::segment($id) . Params::query($params)
        );
    }
}
