# Roadmap — Integrations & AI

Post-v1 thinking. v1 is complete and green (queue-and-claim, fixed pipeline,
notes/@mentions, tasks, tags/views, automations, reporting, merge/unmerge,
audit, retention). This file captures where the *next* leverage is: integrations
into the tools the team already uses, AI features that exploit our structured
lead + activity-timeline data, and the product gaps worth filling.

Ordering within each section is roughly value-to-effort, best first.

---

## 1. Slack (product integration — recommended first)

The team lives in Slack, and the app is already an event engine — we emit
notifications for new-lead-in-queue, claimed, stage change, task due, and
@mention. Piping those into Slack is a small addition with outsized payoff.

- **New lead in queue → `#leads` channel with a Claim button.** The killer
  feature. Speed-to-lead is a dashboard metric; claiming from Slack in seconds
  beats logging in. Slack interactive message → existing `LeadController@claim`
  (already atomic).
- **@mention in a note → Slack DM** to the mentioned user. We already resolve
  mention user IDs (`TiptapMentions::extractUserIds`).
- **Deal won (terminal stage) → `#wins` post** with value + owner. Cheap morale
  win; drives adoption.
- **Daily digest DM** — "N overdue tasks, M unclaimed leads."

Architecture: add a Slack channel to the notification classes (today's
`MailAndDatabaseChannels` becomes mail + database + Slack), plus one inbound
route for Slack's interaction webhook — modelled on the existing signed-webhook
middleware (`VerifyWebhookSignature`).

## 2. Linear (dev-workflow, not a core CRM integration)

Linear is an engineering tracker; a sales CRM has no natural sync with it. Two
narrow, legitimate uses:

- **Dev-workflow:** track the next CRM slices/bugs as Linear issues (this is the
  real reason Linear is in the picture). Can be driven directly via the Linear
  MCP.
- **Product-feedback bridge (nice-to-have):** a note logged as a customer
  feature request → a Linear issue, linked back, so deal blockers stay visible
  to eng.

Not foundational. Do not build a heavy two-way sync.

---

## AI features

Our data model — structured leads plus a rich Activity timeline of
notes/calls/emails — is ideal for LLM features. Use current Claude models via
the Anthropic PHP SDK, wired as queued jobs (worker + scheduler infra already
exists) so the UI stays snappy. Haiku 4.5 for high-frequency cheap calls
(summaries, drafts); Sonnet where accuracy matters (enrichment, NL→query).

1. **Lead summarization / "catch me up."** One button on lead detail → 3-line
   summary of the timeline + suggested next step. Lowest effort, highest daily
   use — perfect on reassignment or reopening a stale lead. **Start here for AI.**
2. **Draft the follow-up.** In the Tiptap composer, "draft a reply" from lead
   context + last activity. Reps edit-and-send instead of writing cold.
3. **Auto-enrich inbound leads.** LLM step in `ProcessLeadSubmission` parses
   messy free-text ("~50 seats, enterprise plan") into structured fields +
   suggested tag + intent flag. Turns rule-based automation into semantic
   automation.
4. **Natural-language reporting.** "Leads from paid campaigns that went cold this
   month" → params for the existing `Lead::scopeFilter`. High wow-factor; do
   after 1–2 (needs guardrails).
5. **Lead scoring / next-best-action** across the pipeline. Most valuable
   long-term, most effort; needs enough won/lost history to be trustworthy.
   Later.
6. **Voice/chat capture.** "Log a call with Acme, they want a demo Friday" →
   creates the activity + task. Pairs naturally with telephony (below).
7. **Semantic duplicate detection.** Today's merge finds dupes by exact
   email/phone (leads) and name (companies). Embeddings catch fuzzy dupes
   ("Acme Inc" vs "Acme Corporation") the current clustering misses.

---

## Other integrations worth adding

Ranked by how much a sales team actually feels them.

- **Two-way email sync (Gmail / Outlook).** The single biggest gap in most
  CRMs. Emails to/from a lead auto-log as `email` activities (the activity type
  already exists — today it's manual). Turns the timeline from
  self-reported into a true record. Sizeable — its own slice.
- **Calendar (Google/Outlook).** Meetings become activities; upcoming meetings
  surface on the lead; post-meeting task prompts. Pairs with email sync.
- **Telephony (Twilio / Aircall).** Click-to-call, auto-logged call activities,
  recording → transcription → AI summary (ties to AI #6). The `call` activity
  type is already there.
- **Data enrichment (Clearbit / Apollo).** Auto-fill company fields from the
  email domain on inbound. Removes manual data entry; feeds scoring.
- **Outbound webhooks / Zapier.** We have *inbound* webhooks; outbound events
  (lead won, stage changed) let others build on us without bespoke work.
- **Hosted / embeddable lead form + website chat widget.** We ingest
  `LeadFormSubmission` via webhook; owning the capture surface closes the loop.
- **Google Drive attachments.** Link Drive docs alongside uploaded attachments
  for teams that keep collateral there.
- **E-signature (DocuSign) / billing (Stripe).** Later-stage, contract-and-money
  end of the funnel. Only if deals routinely involve signed docs/invoices.

## Product gaps (no integration required)

- **Email sequences / cadences.** Multi-step automated outreach — a core CRM
  feature we don't have. Big, but high value for inbound follow-up.
- **Auto-assignment (round-robin / rules).** An alternative to queue-and-claim:
  auto-distribute leads by rule or fairly across reps. Speeds first touch.
- **Custom fields.** Let admins add fields without code. Broadens fit.
- **Deal value line items / products / quotes.** Richer than a single value on
  the lead, if deals have multiple products.
- **Quotas & goals.** We have a leaderboard; quota-vs-actual tracking is the
  natural next step.
- **Mobile / PWA.** Reps in the field. The dense desktop UI is the priority, but
  a read-plus-claim mobile view has value.
- **XLSX / PDF export.** Deferred in v1 (env-blocked: maatwebsite/excel
  uninstallable on L13/PHP 8.5; spatie/laravel-pdf needs Chromium). Revisit with
  a different lib or a headless-Chromium service.

---

## Suggested sequencing

1. **Slack `#leads` + Claim button** — adoption + the speed-to-lead metric.
2. **AI "catch me up" summary** — cheapest AI win, used constantly.
3. **Two-way email sync** — the biggest structural gap; makes everything else
   (AI drafts, scoring) more accurate because the timeline becomes complete.

Everything after that depends on what the team feels most — telephony if they're
phone-heavy, enrichment if data entry is the pain, cadences if follow-up is
dropping.
