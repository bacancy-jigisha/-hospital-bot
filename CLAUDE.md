# Hospital Information Bot — conventions

An agentic RAG chatbot for hospital visitor/patient questions. See
`../hospital-bot-prompt.md` for the full build spec and phase plan; this file
records the conventions actually settled on while building it, and is kept
current as phases land.

## Stack decisions (and why they differ from the spec's literal wording)

- **Laravel 12**, not 13 — this machine runs PHP 8.2.27; Laravel 13 requires
  PHP 8.3+. Revisit if the toolchain is ever upgraded.
- **Vite 5.4.x**, not the newest major — Vite 8 requires Node 20.19+/22.12+;
  this machine has Node 20.18.0.
- **React 18.3.x**, pinned explicitly — `create-vite` defaults to React 19;
  the spec calls for React 18.
- **Google Gemini, not OpenAI**, for embeddings and (from Phase 7) chat —
  changed at Phase 5. The spec names OpenAI, but the available OpenAI key
  had no usable quota (429 on every request, even after retries), and the
  author asked to switch to a free Google AI Studio key instead. There's no
  dominant first-party Gemini PHP SDK, so `EmbeddingService` (and later
  `LlmService`) call the REST API directly via Laravel's `Http` client —
  see `config/gemini.php` (connection) and `config/rag.php` (model names,
  read from `GEMINI_EMBEDDING_MODEL` / `GEMINI_CHAT_MODEL`). Embedding model
  verified against current Gemini docs at switch time: `gemini-embedding-001`.
  `openai-php/laravel` was removed since nothing uses it.
- Backend dev server runs on **port 8001**, not 8000 — an unrelated project
  on this machine already holds 8000.
- **`RAG_MIN_SIMILARITY=0.55`, not the spec's literal `0.35`** — tuned at
  Phase 6 for `gemini-embedding-001`'s actual score distribution rather than
  reusing a value chosen for OpenAI's. On the sample handbook, on-topic
  queries scored ~0.64-0.66 cosine similarity; a clearly off-topic query
  ("how do I bake a chocolate cake") still scored 0.48 — too close to trust
  0.35 as a cutoff. This is a single small manual test, not a real eval set
  (the spec's own "no evaluation harness" limitation applies directly here)
  — revisit if real usage shows too many false "not found"s or too many
  ungrounded matches.
- **`GEMINI_CHAT_MODEL=gemini-3.5-flash-lite`, not the newest `gemini-3.8-flash`**
  — found at Phase 7 via a live 429: `gemini-3.8-flash`'s free tier allows
  only **20 requests/day** per project (`GenerateRequestsPerDayPerProjectPerModel-FreeTier`),
  exhausted almost immediately during development. `gemini-3.5-flash-lite`
  is explicitly documented as optimized for agentic/tool-calling workloads
  and has a 500/day free quota — 25x more headroom. Quotas are tracked
  per-model, so this is a config change only, no code impact.

### Gemini function-calling gotchas (generateContent API)

Three real, non-obvious protocol requirements found only by making live
calls and reading the actual error bodies — not documented clearly enough
anywhere to have caught them by reading first (see `LlmService`):

1. **`functionResponse.response` must be a JSON object, not an array.**
   Several tools return a plain list (e.g. search results). Sending that
   list directly as `response` fails with *"Proto field is not repeating,
   cannot start list"* — it must be wrapped, e.g. `{"result": [...]}`.
2. **`functionCall.args` must also be an object, even when empty.** PHP's
   `[]` is ambiguous between JSON `{}` and `[]`; a tool called with no
   arguments needs `(object) $arguments` before encoding, or the same
   "cannot start list" error occurs.
3. **Gemini 3.x models require a `thoughtSignature` to be echoed back.**
   The model attaches a `thoughtSignature` string as a *sibling* of
   `functionCall` in its response `part` (not nested inside it). When that
   assistant turn is replayed in a later request (after executing the
   tool), the exact same `thoughtSignature` must be included as a sibling
   field again, or the request is rejected outright: *"Function call is
   missing a thought_signature in functionCall parts."* Confirmed by
   inspecting Gemini's actual raw JSON response directly — official doc
   pages on this were inconsistent/incomplete when fetched.

Also chose `generateContent` (function calling marked "Legacy" in current
docs) over Google's newer **Interactions API**: the Interactions API's
docs were inconsistent across fetches (even contradicting itself on
whether `temperature` exists) and it's built around server-side state
(`previous_interaction_id`), which fits poorly with this project's
explicit requirement to self-manage the "last 6 messages" window and
build our own `tool_trace`. `generateContent` is stable, thoroughly
documented, and confirmed to work with the newest models.

`LlmService` retries 429/5xx up to 3 times with backoff — not spec'd
explicitly for it (only `EmbeddingService` was), but added after live
testing showed real transient 503s ("high demand") from this model.

- **`frontend`'s `oxlint` doesn't run on this machine** — its native binding
  has no build for Node 20.18 (the same Vite 5/Node 20.18 constraint from
  Phase 0). Not a project-specified linter (Pint is, per the spec's rule
  7), so this doesn't block anything; `vite build` is used instead to
  catch real compile errors. Revisit if Node is ever upgraded.
- Added `DocumentController::status()` and `retry()` at Phase 8 — not
  built earlier since no prior phase's UI needed them, per "work one phase
  at a time." `status()` is functionally identical to `index()`; the
  separate route just makes the frontend's polling intent explicit.
  `retry()` only allows retrying a `failed` document (422 otherwise).

## Project layout

- `app/` — standard Laravel structure: Controllers/Api, Requests, Resources,
  Jobs, Models, Services, Services/Tools, Exceptions.
- `frontend/` — separate React 18 + Vite SPA, its own `package.json`. No
  Inertia, no Blade views, no `laravel-vite-plugin`. Talks to the backend
  only via `frontend/src/api/*` (Axios), never by importing backend code.
- `config/rag.php` — created when the RAG pipeline needs it (Phase 4+); all
  RAG tuning knobs read from `.env`, never hardcoded. `config/gemini.php`
  (Phase 5+) holds the Gemini API connection details separately.
- `database/seeders/samples/hospital-handbook.pdf` — a real, text-extractable
  (not scanned) sample PDF for manually exercising the upload → parse →
  chunk → embed pipeline once it exists. It is **not** seeded into the
  `documents` table — documents only ever enter that table through the real
  upload endpoint, so the seeder doesn't bypass the pipeline it's meant to
  demonstrate.

## Data model

Two kinds of data, kept deliberately separate:

- **Knowledge base** (`documents`, `document_chunks`): free text from
  uploaded PDFs, chunked and embedded, searched by `VectorSearchService`.
- **Hospital records** (`departments`, `doctors`, `services`): structured
  facts, seeded once by `HospitalSeeder`, queried directly by agent tools
  (`find_doctors`, `get_department_info`, `list_services`,
  `get_visiting_hours`). Never embedded — there's no ambiguity to resolve by
  similarity search when the answer is a row lookup.

`services.department_id` is nullable (some services, like the ambulance or
the pathology lab, aren't tied to one department). `doctors.department_id`
is required.

Model casts of note: `DocumentChunk.embedding`, `Message.sources`,
`Message.tool_trace`, and `Doctor.available_days` are all cast to `array`
(stored as JSON columns).

## Conventions

- Controllers stay thin: Controller → FormRequest → Service → Model. No
  repository layer — not needed at this scale.
- Constructor injection throughout; no service locator calls.
- Prefer obvious code over clever code — this is a learning project the
  author needs to be able to explain end to end. Comment RAG/agent concepts
  (chunk, embedding, vector search, tool calling, agent loop, grounding)
  where they first appear in code, not everywhere.
- No starter kits, no Breeze/Jetstream, no packages beyond what the spec
  names (`smalot/pdfparser`) plus whatever Laravel's own installer adds
  (Sanctum ships with `install:api` by default). Gemini is called via
  Laravel's own `Http` client, not a third-party SDK — see the Gemini note
  above.

## Verifying each phase

- Phase 1: `php artisan migrate:fresh --seed` — expect 6 departments, 12
  doctors, 10 services, no errors.
- Later phases: see the phase table in `../hospital-bot-prompt.md`.
