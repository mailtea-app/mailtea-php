<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;
use Mailtea\MailteaException;

/**
 * A publication's image library. Reach it at `$mailtea->assets`.
 *
 * An email or site image needs an absolute URL, so this is how a picture that is
 * not already on the web gets one. Pointing an image at a host you do not
 * control breaks the day that host moves the file.
 *
 * PNG, JPEG, GIF, WebP or SVG, 5 MB per image. The bytes are checked against the
 * declared `content_type`, so a mislabelled file is rejected rather than stored.
 */
final class Assets
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * Upload an image. `content` is base64 — see {@see self::uploadFile()} to
     * hand over a path instead.
     *
     * PHP has no separate bytes type, so the SDK cannot tell raw bytes from
     * base64 the way a Python or Go caller's types do. Rather than guess, this
     * takes what the wire takes.
     *
     * @param array<string, mixed> $params `publication_id`, `content` (base64),
     *                                     `content_type`, `filename`.
     *
     * @return array<string, mixed> The stored asset, including its `url`.
     */
    public function upload(array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/assets', $params);
    }

    /**
     * Upload an image from a path: read it, base64-encode it, and fill in the
     * filename when you did not.
     *
     * @param array<string, mixed> $params At least `publication_id` and
     *                                     `content_type`; anything you set here
     *                                     wins over what is inferred.
     *
     * @return array<string, mixed>
     *
     * @throws MailteaException when the file cannot be read.
     */
    public function uploadFile(string $path, array $params): array
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new MailteaException('Could not read ' . $path, 0, 'asset_file_unreadable');
        }

        return $this->upload([
            'filename' => basename($path),
            ...$params,
            'content' => base64_encode($bytes),
        ]);
    }

    /**
     * List the library, newest first. Filters: `publication_id` (required),
     * `search` (file name), `limit` (1-200, default 100).
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/assets' . Params::query($params));
    }

    /**
     * Retire an asset.
     *
     * The stored file is KEPT and its URL keeps resolving, so images inside
     * already-sent emails do not break. This hides the asset from the library —
     * it does not remove it from any email, template or page referencing it.
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
            '/v1/assets/' . Params::segment($id) . Params::query($params)
        );
    }
}
