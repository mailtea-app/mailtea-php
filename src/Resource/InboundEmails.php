<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;

/**
 * Inbound (received) email. Reach it at `$mailtea->emails->inbound`.
 *
 * List and retrieve mail delivered to your receiving domains, download
 * attachments, and {@see self::reply()} — which threads correctly by
 * construction and reuses the transactional send pipeline. Scoped to a
 * publication: pass `publication_id` to {@see self::list()}.
 */
final class InboundEmails
{
    private const BASE = '/v1/emails/inbound';

    /** Attachments on a received email. */
    public readonly InboundAttachments $attachments;

    public function __construct(private readonly Requester $api)
    {
        $this->attachments = new InboundAttachments($api);
    }

    /**
     * List received emails in a publication, most recent first, cursor-paginated.
     *
     * Takes `publication_id`, optional `limit` (1-100, default 20) and `cursor`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', self::BASE . Params::query($params));
    }

    /**
     * Retrieve one received email, including its body, headers, and attachments.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', self::BASE . '/' . Params::segment($id));
    }

    /**
     * Reply to a received email.
     *
     * The reply target (`to`), the threading headers, and the `Re: ` subject
     * default are all derived server-side — pass only the content (`html`/`text`,
     * and optionally `from`, `subject`, `cc`, `bcc`, `idempotency_key`).
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> The resulting transactional email's `id` and `status`.
     */
    public function reply(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            self::BASE . '/' . Params::segment($id) . '/reply',
            $params
        );
    }
}
