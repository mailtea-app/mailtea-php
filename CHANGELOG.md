# Changelog

All notable changes to the `mailtea/mailtea` PHP package are documented here.

## 0.1.0 (2026-08-27)

First release. The official PHP SDK for Mailtea — a thin, typed wrapper over the
REST API, on PHP 8.1+ with `ext-curl` and `ext-json` and no Composer
dependencies.

### Added

- **`Mailtea\Mailtea`** — the client. The API key comes from the constructor or
  `MAILTEA_API_KEY`; the base URL from the constructor or `MAILTEA_API_BASE_URL`,
  for local dev and self-hosted installs. Every request carries
  `User-Agent: mailtea-php/<version>`.

- **Every resource the REST API exposes**, each reachable as a property on the
  client: `emails` (send, batch, get, list, analytics, update, reschedule,
  cancel, plus `inbound` with its `attachments`), `contacts`, `segments`,
  `topics`, `posts`, `senders`, `assets`, `suppressions` (including a CSV
  `export`), `templates` (including versions and restore), `domains` (with
  `tracking`), `webhooks`, `contactProperties`, `apiKeys`, `automations`,
  `automationRuns`, `events` and `eventDefinitions`.

- **Typed request payloads** in `Mailtea\Request` for the calls people make
  daily — `SendEmail`, `BatchEmail`, `UpdateEmail`, `CreateContact`,
  `UpdateContact`, `CreatePost`, `SendPost`, `SendTestPost`, `CreateTopic`. Every
  method that takes one also takes a plain wire-format array, so a field the SDK
  does not model yet never blocks a caller.

- **`Mailtea\MailteaException`** — one exception for every failure, carrying the
  HTTP `status` (0 when the request never got a response), the API's message, a
  machine-readable `errorCode`, the `details` list from a validation failure, and
  the `requestId` from the response's `x-request-id`.

- **`Mailtea\WebhookSigning`** — Standard Webhooks signing and verification with
  a constant-time compare, a five-minute default tolerance, and an injectable
  clock. Verified against a golden vector from the platform's own signer.

- **`Mailtea\Transport`** — an interface for routing the SDK's calls through your
  own HTTP client or a fake. `Mailtea\CurlTransport` is the default.

### Notes

- `emails->cancel()` is `POST /v1/emails/{id}/cancel`. The API has never had a
  `DELETE` on emails.
- A send is capped at 50 recipients combined across `to`, `cc` and `bcc`.
- `suppressions->export()` returns raw CSV text; every other method returns a
  decoded associative array, and a 200 with an empty body comes back as `[]`.
