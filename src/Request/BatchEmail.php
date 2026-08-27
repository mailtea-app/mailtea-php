<?php

declare(strict_types=1);

namespace Mailtea\Request;

/**
 * One item in a `POST /v1/emails/batch` request.
 *
 * A separate type from {@see SendEmail} because the batch endpoint is genuinely
 * narrower, and a field it does not read is worse than absent — it looks like it
 * worked. Batch has no `sender_id` (name your From in `$from`) and no
 * `scheduled_at` (schedule single sends, one per message).
 *
 * The 50-recipients-combined cap applies to each item, and a batch takes at most
 * 100 items.
 */
final class BatchEmail implements Payload
{
    /**
     * @param string|list<string>      $to
     * @param string|list<string>|null $cc
     * @param string|list<string>|null $bcc
     * @param string|list<string>|null $replyTo
     * @param array<string, string|int|float>|null $variables
     * @param list<array{name: string, value: string}>|null $tags
     * @param array<string, string>|null $headers
     */
    public function __construct(
        public readonly string $from,
        public readonly string|array $to,
        public readonly string $subject,
        public readonly ?string $html = null,
        public readonly ?string $text = null,
        public readonly ?string $templateId = null,
        public readonly ?array $variables = null,
        public readonly string|array|null $cc = null,
        public readonly string|array|null $bcc = null,
        public readonly string|array|null $replyTo = null,
        public readonly ?bool $trackingOpen = null,
        public readonly ?bool $trackingClick = null,
        public readonly ?array $tags = null,
        public readonly ?array $headers = null,
    ) {
    }

    public function toArray(): array
    {
        $body = Payloads::compact([
            'from' => $this->from,
            'to' => $this->to,
            'subject' => $this->subject,
            'html' => $this->html,
            'text' => $this->text,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'reply_to' => $this->replyTo,
            'tracking_open' => $this->trackingOpen,
            'tracking_click' => $this->trackingClick,
            'tags' => $this->tags,
            'headers' => $this->headers,
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
