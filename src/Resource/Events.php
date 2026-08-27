<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * Custom product events — they trigger automations and resume
 * `wait_for_event` steps. Reach it at `$mailtea->events`.
 *
 * Scoped to a publication — every call carries a `publication_id`.
 */
final class Events
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * Record an event for a contact. Takes `publication_id`, `name`, and exactly
     * one of `contact_id` or `email` (both is a 400 `contact_reference_conflict`,
     * neither a 400 `contact_reference_required`). Optional: `create_contact`,
     * `properties`, `occurred_at`, `idempotency_key`.
     *
     * `create_contact` is **opt-in** — without it an unresolvable address is a
     * 404 `contact_not_found` rather than a new contact.
     *
     * Returns 202 with `enrolled_automations` and `resumed_runs`. A replay of the
     * same `idempotency_key` returns the ORIGINAL event id with `replayed: true`
     * and always reports zero for both counters. And `resumed_runs: 0` on a FRESH
     * ingest does not prove nothing matched — a run being advanced concurrently
     * is invisible for that instant, so read the run itself
     * (`$mailtea->automationRuns->get(...)`) rather than the counter.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function send(array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/events', $params);
    }

    /**
     * List recorded events, cursor-paginated. Filters: `publication_id`
     * (required), `name`, `contact_id`, `limit`, `after`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/events' . Params::query($params));
    }
}
