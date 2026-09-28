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
     * Editing a published template no longer unpublishes it: the change is
     * saved as the working copy, the template keeps its published status, and
     * the published version keeps sending until {@see self::publish()} is
     * called again. The reply's `unpublished` is kept for compatibility and is
     * always `false` now; check `has_unpublished_versions` on the reply
     * instead (it also carries `message` when that is `true`).
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
     * that the template was published once, not that it still is. This is now
     * the only way to stop a published template sending, short of deleting it
     * (editing or restoring it no longer does that on its own). It also drops
     * the published version, so the next {@see self::publish()} starts from
     * the current (working) content. Requires `publication_id`.
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
     * Entries are metadata only. The design document is never included,
     * because one entry alone can carry half a megabyte of it. `from` and
     * `reply_to` are the sender the version holds, and a change to only the
     * From or Reply-To records a version (or folds into the open one, like
     * any edit). `sender_recorded` says what a
     * `null` means: `true`, the version had none and restoring it clears
     * them; `false`, the version was recorded before versions kept the
     * sender. `is_current` marks the entry that
     * matches the working copy (the saved design being edited), which is not
     * always the newest entry: a metadata-only update touches the template
     * without recording a version. `is_published` (a bool) marks the entry
     * automations and the API are sending now. They differ while a published
     * template has unpublished changes. `is_published` is `false` on every
     * entry of a draft, and on every entry of a template published before the
     * field existed until it is published again.
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
     * Put an older design from {@see self::versions()} back onto the template,
     * with the version's From and Reply-To. A version with `sender_recorded`
     * `false` (recorded before versions kept the sender) leaves the current
     * From and Reply-To as they are. Requires `publication_id`.
     *
     * **Restoring no longer unpublishes the template.** It is a content write,
     * and lands in the working copy: a published template keeps its published
     * status and keeps sending its published version until
     * {@see self::publish()} makes the restored design live. The reply's
     * `unpublished` is kept for compatibility and is always `false` now; check
     * `has_unpublished_versions` on the returned `template` (or the reply's
     * `message`) to see whether the restored design is live yet.
     *
     * History is forward-only: the design being replaced is recorded as its own
     * version first, then the restored design is appended as the new newest one.
     * So a restore is itself undone by restoring the entry directly above it.
     *
     * Restoring the design that is already current writes nothing and returns
     * `restored: false` with `reason: "identical"`.
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
