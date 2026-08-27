<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * The team-wide do-not-send list. Reach it at `$mailtea->suppressions`.
 *
 * Suppressions are scoped to the team, not a publication — there is no
 * `publication_id` here. An address on this list is skipped by every send from
 * every publication on the team.
 */
final class Suppressions
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * List suppression entries, cursor-paginated. Optional filters: `reason`,
     * `q` (email search), `created_after`, `created_before`, `limit`,
     * `starting_after` (a cursor from a previous `next_cursor`).
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/suppressions' . Params::query($params));
    }

    /**
     * Add addresses to the suppression list. Takes `emails` (up to 1000) and an
     * optional `reason`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> `['added' => …]`
     */
    public function add(array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/suppressions', $params);
    }

    /**
     * Remove addresses from the suppression list. Takes `emails`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> `['removed' => …]`
     */
    public function remove(array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('DELETE', '/v1/suppressions', $params);
    }

    /**
     * Export the whole suppression list as CSV.
     *
     * Returns the raw `text/csv` body — `email,reason,source,created_at` with a
     * header row — not JSON, and not an array. This is the one method in the SDK
     * that does not hand back a decoded payload.
     */
    public function export(): string
    {
        /** @var string */
        return $this->api->request('GET', '/v1/suppressions/export', null, raw: true);
    }
}
