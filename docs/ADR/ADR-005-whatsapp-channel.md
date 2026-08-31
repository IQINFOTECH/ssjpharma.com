# ADR-005 — WhatsApp Channel

**Status:** Accepted (2026-08-29). Supersedes nothing. Constrained by ADR-001.
**Context phase:** Phase 5 (Communications & Follow-up Operations).

## Decision

WhatsApp is provided **exclusively via `wa.me` click-to-chat links**. The site (and CMS-managed WhatsApp templates) generate `https://wa.me/<number>?text=<prefilled>` URLs; the **visitor initiates** every conversation in their own WhatsApp client. The business number is read from CMS settings, normalised (digits only) and validated before a link is built — it is never hardcoded.

The **WhatsApp Cloud API / Business API is NOT IMPLEMENTED** and must not be built in Phase 5.

## Current approach — wa.me click-to-chat

**Advantages**
- No API infrastructure, tokens, webhooks or phone-number registration.
- No provider dependency or per-message cost.
- Simple and robust on GoDaddy shared hosting (static links; nothing to run).
- User-initiated → privacy-friendly; no unsolicited messaging.

**Limitations (accepted)**
- No delivery/read status.
- No inbound message synchronisation into the CRM.
- No automated/outbound messaging.
- No conversation history stored in the app.

Because of these limits, the system **only ever records a wa.me CTA _click_** (best-effort, via `POST /whatsapp/track`, CSRF-protected + rate-limited). It **never claims a WhatsApp message was delivered, received, or answered** — a click is not a conversation.

## Future option — WhatsApp Cloud API (NOT IMPLEMENTED)

Should two-way messaging, templated notifications, or delivery receipts become a business requirement, the WhatsApp Cloud API (Meta) could be integrated **directly over HTTPS from PHP** (consistent with ADR-001 — no n8n/Zapier/Make/third-party automation). That would add: a provider dependency, credentials in `.env`, a webhook endpoint, message/template logging, and opt-in/consent handling.

**This is explicitly out of scope for Phase 5 and is not built.** Revisit as its own ADR + phase if/when required.

## Consequences

- WhatsApp templates are CMS-managed text used **only** to pre-fill link messages (`{{product.name}}`, `{{product.url}}`, `{{site.name}}`), containing no sensitive visitor data.
- Reporting on WhatsApp is limited to click counts (`whatsapp_clicks`), clearly distinct from email delivery which the queue tracks precisely.
