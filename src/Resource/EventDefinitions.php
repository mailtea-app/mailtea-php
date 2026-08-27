<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * The catalog of event names a publication expects, with optional property
 * schemas. Reach it at `$mailtea->eventDefinitions`.
 *
 * Scoped to a publication — every call carries a `publication_id`. Definitions
 * are documentation and tooling, not a gate: {@see Events::send()} accepts an
 * event with no definition.
 */
final class EventDefinitions
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * Create an event definition. Takes `publication_id` and `name`, plus
     * optional `description` and `schema_json`. The name is immutable once
     * created.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/event-definitions', $params);
    }

    /**
     * List event definitions, cursor-paginated. Filters: `publication_id`
     * (required), `limit`, `after`. List items carry no `inferred_properties` —
     * use {@see self::get()} for those.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/event-definitions' . Params::query($params));
    }

    /**
     * Retrieve one definition. Requires `publication_id`.
     *
     * Adds `schema_properties` and `inferred_properties` — the latter computed on
     * read over the last 500 events, reporting each key's type, sample count and
     * **coverage**. Low coverage is the trap: a condition on a key present in 3%
     * of events will almost never match.
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
            '/v1/event-definitions/' . Params::segment($id) . Params::query($params)
        );
    }

    /**
     * Update a definition's `description` or `schema_json` (null clears the
     * schema back to free-form). `publication_id` is required and is sent as a
     * query parameter. `name` is immutable — sending it is a 400
     * `event_name_immutable`, not a silently dropped rename.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        [$publicationId, $body] = Params::take($params, 'publication_id');

        /** @var array<string, mixed> */
        return $this->api->request(
            'PATCH',
            '/v1/event-definitions/' . Params::segment($id)
                . Params::query(['publication_id' => $publicationId]),
            $body
        );
    }

    /**
     * Delete an event definition. Requires `publication_id`. Events already
     * recorded under that name are untouched.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function delete(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'DELETE',
            '/v1/event-definitions/' . Params::segment($id) . Params::query($params)
        );
    }
}
