<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * Reusable server-side email templates. Reach it at `$mailtea->templates`.
 *
 * Scoped to a publication — every call carries a `publication_id` except
 * {@see self::render()}, which just renders a spec. Create a template from raw
 * `html`, a json-render `spec`, or an `editor_doc` (a Studio editor design),
 * then {@see self::publish()} it before seeding posts or emails from it.
 */
final class Templates
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * Render a json-render `spec` (with optional `variables`) to HTML without
     * creating a template.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> `['html' => …, 'text' => …]`
     */
    public function render(array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/templates/render', $params);
    }

    /**
     * Create a template from `html`, a `spec`, OR an `editor_doc` — exactly one.
     * The server renders `html` from an `editor_doc`, so do not send both.
     *
     * Takes `publication_id` and `name`, plus optional `style_profile`,
     * `mailtea_theme`, `global_css`, `category`, `preview_image_url`, `tags`,
     * `description`, `text`, `subject`, `from`, `reply_to` and `variables`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/templates', $params);
    }

    /**
     * List templates, cursor-paginated. Filters: `publication_id` (required),
     * `limit`, `after`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/templates' . Params::query($params));
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
            '/v1/templates/' . Params::segment($id) . Params::query($params)
        );
    }

    /**
     * Update a template. `global_css`, `category`, `preview_image_url`, `tags`,
     * `text`, `subject`, `from` and `reply_to` accept null to clear them, which
     * is why this takes a plain array: an omitted key and a null one differ.
     * `publication_id` is required and is sent as a query parameter.
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
            '/v1/templates/' . Params::segment($id)
                . Params::query(['publication_id' => $params['publication_id'] ?? null]),
            $params
        );
    }

    /**
     * Publish a template so it can seed posts and emails. Requires
     * `publication_id`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function publish(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            '/v1/templates/' . Params::segment($id) . '/publish' . Params::query($params)
        );
    }

    /**
     * Return a published template to draft. `published_at` is kept — it records
     * that the template was published once, not that it still is. Requires
     * `publication_id`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function unpublish(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            '/v1/templates/' . Params::segment($id) . '/unpublish' . Params::query($params)
        );
    }

    /**
     * List a template's design history, newest first. Requires `publication_id`;
     * optional `limit` (the server caps it at the retained maximum).
     *
     * Entries are metadata only — never the design document, which one entry
     * alone can carry half a megabyte of. `is_current` marks the design the
     * template is serving right now, which is not always the newest entry: a
     * metadata-only update touches the template without recording a version.
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
            '/v1/templates/' . Params::segment($id) . '/versions' . Params::query($params)
        );
    }

    /**
     * Put an older design from {@see self::versions()} back onto the template.
     * Requires `publication_id`.
     *
     * **Restoring is a content write, so the template returns to draft** —
     * automations and the API stop sending it until {@see self::publish()} is
     * called again. The reply's `unpublished` reports whether that just
     * happened; re-publishing is the caller's job.
     *
     * History is forward-only: the design being replaced is recorded as its own
     * version first, then the restored design is appended as the new newest one.
     * So a restore is itself undone by restoring the entry directly above it.
     *
     * Restoring the design that is already current writes nothing and returns
     * `restored: false` with `reason: "identical"`, so a no-op restore cannot
     * unpublish a live template.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function restoreVersion(string $id, int|string $version, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            '/v1/templates/' . Params::segment($id)
                . '/versions/' . Params::segment((string) $version)
                . '/restore' . Params::query($params)
        );
    }

    /**
     * Duplicate a template into a new draft. Requires `publication_id`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function duplicate(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            '/v1/templates/' . Params::segment($id) . '/duplicate' . Params::query($params)
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
            '/v1/templates/' . Params::segment($id) . Params::query($params)
        );
    }
}
