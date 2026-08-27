<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * API keys. Reach it at `$mailtea->apiKeys`.
 *
 * Requires a token with `settings:write`. A key can never be granted scopes the
 * calling token does not already hold, so this cannot be used to escalate.
 */
final class ApiKeys
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * Create an API key. The `token` is returned ONCE — store it immediately.
     *
     * Takes `name`, optional `permission` (`full_access` or `sending_access`),
     * and optional `domain_id`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/api-keys', $params);
    }

    /**
     * List API keys. Token values are never returned.
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/api-keys');
    }

    /**
     * Revoke (delete) an API key by id.
     *
     * @return array<string, mixed>
     */
    public function revoke(string $id): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('DELETE', '/v1/api-keys/' . Params::segment($id));
    }
}
