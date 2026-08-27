<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * Multi-step contact journeys. Reach it at `$mailtea->automations`.
 *
 * Scoped to a publication — every call carries a `publication_id`. An automation
 * is a graph: `steps` (each `['key', 'type', 'label', 'config']`) plus optional
 * `connections` (each `['from', 'to', 'branch']`).
 *
 * `connections` is optional: omit it and the server links the steps in array
 * order with `branch: "next"`, rooted at the trigger. A graph containing a
 * `condition` or `wait_for_event` step cannot be inferred that way and is
 * rejected with `connections_required_for_branching` — send its connections
 * explicitly.
 *
 * Failures come back as coded `issues[]` rather than schema errors, and for a
 * draft/paused/archived automation they ride along informationally instead of
 * blocking the save.
 */
final class Automations
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * Dry-run a graph without creating anything. Takes `publication_id` and
     * `steps`, plus optional `connections`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> `['object' => 'automation_validation', 'valid' => …, 'issues' => […]]`
     */
    public function validate(array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/automations/validate', $params);
    }

    /**
     * Create an automation. Takes `publication_id`, `name` and `steps`, plus
     * optional `description`, `connections`, `reentry_policy`
     * (`once`/`once_per_window`/`always` — `once_per_window` requires
     * `reentry_window_seconds`), `on_step_failure` and `validate_only`.
     *
     * New automations start as `draft` — {@see self::activate()} starts them.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/automations', $params);
    }

    /**
     * List automations, cursor-paginated. Filters: `publication_id` (required),
     * `status`, `limit`, `after`. List items omit `steps`, `connections`,
     * `valid` and `issues` — use {@see self::get()} for the full graph.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/automations' . Params::query($params));
    }

    /**
     * Retrieve one automation with its live graph and current `issues[]`.
     * Requires `publication_id`.
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
            '/v1/automations/' . Params::segment($id) . Params::query($params)
        );
    }

    /**
     * Update an automation. The graph is replaced wholesale and cuts a new
     * version. `publication_id` is required and is sent as a query parameter —
     * NOT in the body, which the schema rejects.
     *
     * `validate_only: true` returns an `automation_validation` and writes
     * nothing. A graph change carrying errors saves anyway while the automation
     * is draft/paused/archived; on an `active` one it is a 422 — pause, save,
     * then start again.
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
            '/v1/automations/' . Params::segment($id)
                . Params::query(['publication_id' => $publicationId]),
            $body
        );
    }

    /**
     * Delete an automation. Requires `publication_id`. Deleting an `active`
     * automation is a 409 `automation_active` — pause or archive it first, so
     * its in-flight runs are not dropped silently.
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
            '/v1/automations/' . Params::segment($id) . Params::query($params)
        );
    }

    /**
     * Start the automation so new contacts enroll. Requires `publication_id`. A
     * graph with errors is refused with 422 `automation_invalid` and the
     * blocking `issues[]`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function activate(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            '/v1/automations/' . Params::segment($id) . '/activate' . Params::query($params)
        );
    }

    /**
     * Stop new enrollments. Requires `publication_id` (query). Optional
     * `cancel_runs` — it **defaults to false** here, so in-flight runs keep
     * going; pass `['cancel_runs' => true]` to exit them.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> The automation plus `canceled_runs`.
     */
    public function pause(string $id, array $params = []): array
    {
        return $this->lifecycle($id, '/pause', $params);
    }

    /**
     * Archive the automation. Requires `publication_id` (query). Optional
     * `cancel_runs` — it **defaults to true** here, the opposite of
     * {@see self::pause()}, so in-flight runs exit with `automation_archived`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> The automation plus `canceled_runs`.
     */
    public function archive(string $id, array $params = []): array
    {
        return $this->lifecycle($id, '/archive', $params);
    }

    /**
     * List an automation's versions, cursor-paginated. Filters:
     * `publication_id` (required), `limit`, `after`. List items carry no
     * `steps`/`connections` — use {@see self::version()} for a stored graph.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function versions(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'GET',
            '/v1/automations/' . Params::segment($id) . '/versions' . Params::query($params)
        );
    }

    /**
     * Retrieve one stored version, including its `steps` and `connections`.
     * Requires `publication_id`. This is the graph a run of that version is
     * pinned to — editing the automation never rewrites it.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function version(string $id, int|string $version, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'GET',
            '/v1/automations/' . Params::segment($id)
                . '/versions/' . Params::segment((string) $version)
                . Params::query($params)
        );
    }

    /**
     * Per-step funnel counts. Filters: `publication_id` (required), `version`
     * (omit to aggregate across ALL versions), `since`, `until` (ISO 8601). Test
     * runs are always excluded. Condition steps report
     * `branches: {condition_met, condition_not_met}`, `wait_for_event` steps
     * `{event_received, timeout}`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function metrics(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'GET',
            '/v1/automations/' . Params::segment($id) . '/metrics' . Params::query($params)
        );
    }

    /**
     * Run the automation once against a real contact. `publication_id` is
     * required and is sent as a query parameter; the body takes one of
     * `contact_id` or `email`, plus optional `event_properties` to seed the
     * run's `event.*` namespace.
     *
     * A test run **sends real, billed email** to that inbox — it does not bypass
     * any send gate. It is flagged `is_test` and excluded from
     * {@see self::metrics()}. Returns 202 with the queued run.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function test(string $id, array $params): array
    {
        [$publicationId, $body] = Params::take($params, 'publication_id');

        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            '/v1/automations/' . Params::segment($id) . '/test'
                . Params::query(['publication_id' => $publicationId]),
            $body
        );
    }

    /**
     * `publication_id` goes in the query but `cancel_runs` is read from the
     * body, so the two are split here. No body is sent when the caller omitted
     * `cancel_runs` — or passed it as null, which the server's schema would
     * reject — so the per-verb default applies in both cases.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function lifecycle(string $id, string $suffix, array $params): array
    {
        $cancelRuns = $params['cancel_runs'] ?? null;

        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            '/v1/automations/' . Params::segment($id) . $suffix
                . Params::query(['publication_id' => $params['publication_id'] ?? null]),
            $cancelRuns === null ? null : ['cancel_runs' => $cancelRuns]
        );
    }
}
