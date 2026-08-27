<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * One contact's journey through one automation. Reach it at
 * `$mailtea->automationRuns`.
 *
 * Runs are nested under an automation and scoped to a publication — pass the
 * automation id and a `publication_id`. A run PINS the automation version it
 * started on, so {@see self::get()} returns the graph the run is actually
 * executing, not the live one.
 */
final class AutomationRuns
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * List an automation's runs, cursor-paginated. Filters: `publication_id`
     * (required), `status` (one or more run statuses — pass a list and it is
     * comma-joined for you), `contact_id`, `is_test` (a real bool; it goes out
     * as the literal `true`/`false` the server matches), `limit`, `after`.
     *
     * List items omit the pinned graph and the step runs; use {@see self::get()}
     * for those.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(string $automationId, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'GET',
            '/v1/automations/' . Params::segment($automationId) . '/runs' . Params::query($params)
        );
    }

    /**
     * Retrieve one run in full. Requires `publication_id`.
     *
     * Returns the PINNED `steps`/`connections`, the per-step `step_runs`, and
     * `waiting` (`resume_at` / `waiting_event_name`) — read this rather than an
     * event ingest's `resumed_runs` counter to tell whether an event actually
     * advanced the run.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function get(string $automationId, string $runId, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'GET',
            '/v1/automations/' . Params::segment($automationId)
                . '/runs/' . Params::segment($runId)
                . Params::query($params)
        );
    }

    /**
     * Cancel one in-flight run. Requires `publication_id`. A cancelled run
     * cannot be resumed.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> The run in full detail.
     */
    public function cancel(string $automationId, string $runId, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            '/v1/automations/' . Params::segment($automationId)
                . '/runs/' . Params::segment($runId)
                . '/cancel' . Params::query($params)
        );
    }
}
