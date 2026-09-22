# Hospital Information Bot

An agentic RAG chatbot that answers visitor and patient questions about a
hospital — departments, doctors, services, visiting hours, admission and
discharge — grounded in the hospital's own uploaded documents and records.
Laravel 12 API backend + a separate React 18 SPA frontend.

> This README covers **setup and day-to-day usage**. For *why* things are
> built the way they are (stack decisions, provider switches, bugs found
> and fixed along the way), see [`CLAUDE.md`](CLAUDE.md). A fuller
> beginner-friendly explanation of RAG/agent concepts, diagrams, and
> worked examples is planned but not yet written.

## Prerequisites

- PHP 8.2+, Composer
- Node 20+, npm
- MySQL
- A free Gemini API key from [Google AI Studio](https://aistudio.google.com/)
  (no billing required)

Redis is optional — the app defaults to a `sync` queue so it works without
it. See [Queue](#queue) below.

## Backend setup

```bash
cd hospital-bot
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
- Set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` for a MySQL database you've
  created (e.g. `CREATE DATABASE hospital_bot;`).
- Set `GEMINI_API_KEY` to your key from Google AI Studio.
- Set `HOSPITAL_NAME`, `HOSPITAL_RECEPTION_PHONE`, `HOSPITAL_EMERGENCY_PHONE`
  (any values — this is a fictional demo hospital).

Then:

```bash
php artisan migrate --seed   # creates tables + seeds 6 departments, 12 doctors, 10 services
php artisan serve --port=8001
```

The API is now at `http://127.0.0.1:8001/api`.

> **Port 8001, not 8000:** this project's dev environment already had
> something else on 8000. Use whatever port is free on yours — just keep
> the frontend's `VITE_API_BASE_URL` (below) pointing at it.

## Frontend setup

```bash
cd hospital-bot/frontend
npm install
cp .env.example .env   # if present, otherwise create it — see below
```

`frontend/.env`:
```
VITE_API_BASE_URL=http://localhost:8001/api
```

Then:

```bash
npm run dev
```

The app is now at `http://localhost:5173`.

> **CORS:** `config/cors.php` on the backend only allows
> `http://localhost:5173` by default. If you run the frontend on a
> different port, update that file too.

## Running it day to day

Two processes, both from `hospital-bot/`:

```bash
php artisan serve --port=8001   # backend
```
```bash
cd frontend && npm run dev      # frontend, separate terminal
```

A third process (`php artisan queue:work`) is only needed if you switch
`QUEUE_CONNECTION` to `redis` — see [Queue](#queue).

## Using the app

1. Open `http://localhost:5173/documents`.
2. Upload a PDF — the repo includes a ready-made sample:
   `database/seeders/samples/hospital-handbook.pdf`. Give it a title and
   category, and upload.
3. Its status goes `pending` → `completed` automatically (with the default
   `sync` queue, almost instantly). "Chunks" shows how many pieces it was
   split into.
4. Open `http://localhost:5173/` (Chat) and ask something the handbook or
   seeded data covers, e.g.:
   - *"What are the visiting hours?"*
   - *"What should I bring when I'm admitted?"*
   - *"Which doctors specialize in cardiology?"*
5. Expand **"How this answer was produced"** under an answer to see which
   tool(s) the agent called and the similarity scores of any retrieved
   document chunks.
6. Try something outside the knowledge base (e.g. *"Do you offer valet
   parking?"*) — it should say the information wasn't found, not invent
   an answer. Try something like *"I have chest pain"* — it should reply
   instantly with the emergency number, without calling the AI model at
   all.

## Environment variables

All RAG/agent config lives in `config/rag.php` and `config/gemini.php`,
both reading from `.env` — nothing is hardcoded.

| Variable | Meaning |
| --- | --- |
| `GEMINI_API_KEY` | Your Google AI Studio key |
| `GEMINI_EMBEDDING_MODEL` | Model used to embed text for search (default `gemini-embedding-001`) |
| `GEMINI_CHAT_MODEL` | Model used for chat/tool-calling (default `gemini-3.5-flash-lite` — see `CLAUDE.md` for why not the newest model) |
| `RAG_CHUNK_SIZE` | Target characters per document chunk (default `1000`) |
| `RAG_CHUNK_OVERLAP` | Characters carried from one chunk into the next (default `200`) |
| `RAG_TOP_K` | Max chunks returned per search (default `5`) |
| `RAG_MIN_SIMILARITY` | Minimum cosine similarity to keep a chunk (default `0.55` — tuned empirically, see `CLAUDE.md`) |
| `AGENT_MAX_STEPS` | Max tool-calling rounds before forcing a final answer (default `5`) |
| `HOSPITAL_NAME` / `HOSPITAL_RECEPTION_PHONE` / `HOSPITAL_EMERGENCY_PHONE` | Used in the system prompt and emergency responses |
| `QUEUE_CONNECTION` | `sync` (default, no worker needed) or `redis` (needs `php artisan queue:work`) |

## Queue

Document processing (parse → chunk → embed → save) runs through
`ProcessDocumentJob`. By default `QUEUE_CONNECTION=sync` runs it inline —
simplest for local use, no extra process. If you have Redis installed:

```env
QUEUE_CONNECTION=redis
```
```bash
php artisan queue:work
```

## Useful commands for manually inspecting the pipeline

These print intermediate pipeline output directly to the terminal — handy
for seeing exactly what's happening at each stage, independent of the API:

```bash
php artisan document:parse {id}    # extracted text + page count for a document
php artisan document:chunk {id}    # chunk count + first two chunks
php artisan rag:search "some question"   # matching chunks with similarity scores
```

## Tests and linting

```bash
php artisan test        # backend feature/unit tests
./vendor/bin/pint       # backend code style
cd frontend && npm run build   # frontend compile check
```

## Known limitations

- No authentication — a chat session is a client-generated UUID in
  `localStorage`. Add Laravel Sanctum before exposing this beyond a
  laptop.
- No OCR — a scanned PDF (no text layer) is rejected, not processed.
- Single language, brute-force vector search (fine at this scale; see
  `VectorSearchService`'s docblock for the tradeoff), naive
  paragraph-based chunking, no evaluation harness for retrieval quality.

See `CLAUDE.md` for the fuller list of decisions, gotchas, and why things
differ from the original build spec (`../hospital-bot-prompt.md`).
