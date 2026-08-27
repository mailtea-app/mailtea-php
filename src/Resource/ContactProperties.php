<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * Custom contact fields. Reach it at `$mailtea->contactProperties`.
 *
 * Definitions are team-scoped — there is no `publication_id` here. `create`
 * takes `key` and `type` (`string` or `number`).
 */
final class ContactProperties
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
        return $this->api->request('POST', '/v1/contact-properties', $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/contact-properties' . Params::query($params));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'PATCH',
            '/v1/contact-properties/' . Params::segment($id),
            $params
        );
    }

    /** @return array<string, mixed> */
    public function delete(string $id): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('DELETE', '/v1/contact-properties/' . Params::segment($id));
    }
}
