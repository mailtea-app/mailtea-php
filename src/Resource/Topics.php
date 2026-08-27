<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;
use Mailtea\Request\CreateTopic;
use Mailtea\Request\Payloads;

/**
 * Topic definitions. Reach it at `$mailtea->topics`.
 *
 * Scoped to a publication — every call carries a `publication_id`. This manages
 * the definitions only; assigning topics to contacts is not exposed on the REST
 * API yet.
 */
final class Topics
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * Create a topic definition.
     *
     * @param CreateTopic|array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function create(CreateTopic|array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/topics', Payloads::toArray($params));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/topics' . Params::query($params));
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
            '/v1/topics/' . Params::segment($id) . Params::query($params)
        );
    }

    /**
     * Update a topic's `name`, `description`, `default_subscription` or
     * `visibility`. `publication_id` goes in both the query string and the body.
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
            '/v1/topics/' . Params::segment($id)
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
            '/v1/topics/' . Params::segment($id) . Params::query($params)
        );
    }
}
