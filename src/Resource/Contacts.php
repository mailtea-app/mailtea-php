<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;
use Mailtea\Request\CreateContact;
use Mailtea\Request\Payloads;
use Mailtea\Request\UpdateContact;

/**
 * Audience contacts. Reach it at `$mailtea->contacts`.
 *
 * Scoped to a publication — every call carries a `publication_id`. A contact is
 * addressable by id or by email address, so `get('reader@example.com')` works
 * as well as `get('con_…')`.
 */
final class Contacts
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * Create a contact — or update it if the email is already in the publication.
     * The endpoint upserts; {@see self::upsert()} is the same call under the name
     * that says so.
     *
     * @param CreateContact|array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function create(CreateContact|array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/contacts', Payloads::toArray($params));
    }

    /**
     * Create the contact or update it in place — an alias of {@see self::create()},
     * named for what `POST /v1/contacts` actually does.
     *
     * @param CreateContact|array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function upsert(CreateContact|array $params): array
    {
        return $this->create($params);
    }

    /**
     * List contacts, cursor-paginated.
     *
     * Filters: `publication_id` (required), `status`
     * (`active`/`unsubscribed`/`suppressed`), `search` (matches the email
     * address), `limit`, `after` (a cursor from a previous `next_cursor`).
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/contacts' . Params::query($params));
    }

    /**
     * Retrieve a contact by id or email address. Requires `publication_id`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function get(string $idOrEmail, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'GET',
            '/v1/contacts/' . Params::segment($idOrEmail) . Params::query($params)
        );
    }

    /**
     * Update a contact. `publication_id` is required, and travels in both the
     * query string and the body: the route reads it from the query, the schema
     * requires it in the body.
     *
     * @param UpdateContact|array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function update(string $idOrEmail, UpdateContact|array $params): array
    {
        $body = Payloads::toArray($params);

        /** @var array<string, mixed> */
        return $this->api->request(
            'PATCH',
            '/v1/contacts/' . Params::segment($idOrEmail)
                . Params::query(['publication_id' => $body['publication_id'] ?? null]),
            $body
        );
    }

    /**
     * Delete a contact. Requires `publication_id`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function delete(string $idOrEmail, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'DELETE',
            '/v1/contacts/' . Params::segment($idOrEmail) . Params::query($params)
        );
    }
}
