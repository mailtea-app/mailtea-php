<?php

declare(strict_types=1);

namespace Mailtea\Request;

/**
 * The payload for `POST /v1/emails` — one transactional email.
 *
 * Set the From with exactly one of `$from` (a plain address or a
 * `Name <address>` string) or `$senderId` (the id of a named, verified
 * publication sender, which also supplies its default reply-to). Sending both,
 * or neither, is a 400.
 *
 * Give the body as `$html` and/or `$text`, OR as `$templateId` (+ `$variables`)
 * — `html` and a template together are a 400.
 *
 * The provider caps one message at **50 recipients combined** across `to`, `cc`
 * and `bcc`; the API enforces it before anything is sent. `reply_to` is not a
 * recipient and does not count.
 */
final class SendEmail implements Payload
{
    /**
     * @param string|list<string>      $to          One address or a list.
     * @param string|list<string>|null $cc
     * @param string|list<string>|null $bcc
     * @param string|list<string>|null $replyTo
     * @param array<string, string|int|float>|null $variables Values for a template's placeholders.
     * @param bool|null $trackingOpen  Send without the open pixel. A sending
     *                                 domain with tracking switched off cannot
     *                                 be overridden from here — policy narrows,
     *                                 it never widens.
     * @param bool|null $trackingClick Send without rewritten links.
     * @param string|null $scheduledAt ISO 8601. The send stays cancellable until
     *                                 it leaves the queue.
     * @param list<array{name: string, value: string}>|null $tags For filtering and analytics.
     * @param array<string, string>|null $headers Extra custom email headers.
     * @param list<array{filename: string, content: string, content_type?: string, content_id?: string}>|null $attachments
     *        `content` is base64. Set `content_id` (plus `content_type`) to embed
     *        an inline image referenced by `cid:` in the HTML; omit it for a
     *        regular file attachment.
     */
    public function __construct(
        public readonly string|array $to,
        public readonly string $subject,
        public readonly ?string $from = null,
        public readonly ?string $senderId = null,
        public readonly ?string $html = null,
        public readonly ?string $text = null,
        public readonly ?string $templateId = null,
        public readonly ?array $variables = null,
        public readonly string|array|null $cc = null,
        public readonly string|array|null $bcc = null,
        public readonly string|array|null $replyTo = null,
        public readonly ?bool $trackingOpen = null,
        public readonly ?bool $trackingClick = null,
        public readonly ?string $scheduledAt = null,
        public readonly ?array $tags = null,
        public readonly ?array $headers = null,
        public readonly ?array $attachments = null,
    ) {
    }

    public function toArray(): array
    {
        $body = Payloads::compact([
            'from' => $this->from,
            'sender_id' => $this->senderId,
            'to' => $this->to,
            'subject' => $this->subject,
            'html' => $this->html,
            'text' => $this->text,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'reply_to' => $this->replyTo,
            'tracking_open' => $this->trackingOpen,
            'tracking_click' => $this->trackingClick,
            'scheduled_at' => $this->scheduledAt,
            'tags' => $this->tags,
            'headers' => $this->headers,
            'attachments' => $this->attachments,
        ]);

        if ($this->templateId !== null) {
            $body['template'] = Payloads::compact([
                'id' => $this->templateId,
                'variables' => $this->variables,
            ]);
        }

        return $body;
    }
}
