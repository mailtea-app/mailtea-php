<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * Attachments on a received email. Reach it at
 * `$mailtea->emails->inbound->attachments`.
 *
 * Each returned object carries a short-lived signed `download_url`. Fetch the
 * bytes promptly, or ask again — the URL expires, the attachment does not.
 */
final class InboundAttachments
{
    private const BASE = '/v1/emails/inbound';

    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * List an inbound email's attachments, each with a signed download URL.
     *
     * @return array<string, mixed>
     */
    public function list(string $id): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'GET',
            self::BASE . '/' . Params::segment($id) . '/attachments'
        );
    }

    /**
     * Retrieve a single inbound attachment with a signed download URL.
     *
     * @return array<string, mixed>
     */
    public function get(string $id, string $attachmentId): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'GET',
            self::BASE . '/' . Params::segment($id) . '/attachments/' . Params::segment($attachmentId)
        );
    }
}
