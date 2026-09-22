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
