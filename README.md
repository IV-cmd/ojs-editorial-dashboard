# OJS Editorial Dashboard

[![CI](https://github.com/IV-cmd/ojs-editorial-dashboard/actions/workflows/ci.yml/badge.svg)](https://github.com/IV-cmd/ojs-editorial-dashboard/actions/workflows/ci.yml)

A personal learning project: an operational dashboard over OJS 3.6 REST API data for editorial teams. OJS ingestion, persistence, an authenticated JSON API, a Vue dashboard over that API, and Prometheus/Grafana observability are in place; deployment tooling is still to come.

## Status

| Area | Status in repo |
| --- | --- |
| Laravel 12 / PHP 8.3 | Working application |
| MySQL 8 schema, models, factories, seeders | Done |
| OJS 3.6 client with fixture-backed mock mode | Done (mock is the default; no live OJS is called) |
| Idempotent ingestion queue jobs (`ojs:sync`) | Done |
| Authenticated JSON API with editor/viewer roles | Done |
| Vue 3 dashboard (summary, stuck submissions, overdue reviews) | Done (token sign-in) |
| Prometheus metrics, Grafana dashboard, sync run history | Done (local docker compose stack) |
| Production image, Helm, OpenShift, GitLab CI | Not started |
| Pest / Pint / Larastan / Vitest | Configured; CI is on GitHub Actions |

### Mock mode

The OJS client defaults to a fixture-backed mock driver, so the app runs with no OJS instance and no token. Set `OJS_DRIVER=http` with real credentials to talk to a live server. The production environment refuses to boot with the mock driver.

## Setup

Requirements: PHP 8.3, Composer 2, Node.js 22, and MySQL 8. After `php artisan migrate --seed` the app ingests the bundled fixtures with `php artisan ojs:sync`.

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run build
php artisan serve
```

For frontend development, use `npm run dev` instead of `npm run build`.

## Checks

```sh
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
npm test
npm run build
```

## Ingestion

`php artisan ojs:sync` dispatches `SyncSubmissions`, which lists submissions from the configured OJS client and dispatches one `IngestSubmission` job per submission. Each of those fetches the submission detail and passes it to `OjsUpserter`, which writes the submission and its nested review assignments in one database transaction. The scheduler in `routes/console.php` runs the sync hourly; in production a scheduler and a queue worker (`php artisan queue:work`) must both be running.

Writes are upserts keyed on OJS identifiers: `(context_id, ojs_id)` for submissions and `ojs_id` for review assignments. Running the sync again updates existing rows rather than adding new ones, and the unique keys in the schema reject duplicates if two runs overlap.

Upsert-only: a review assignment that disappears from OJS is not deleted locally. Deleting every assignment and reinserting it on each run would change local row IDs and lose the last known state, so pruning is left as a separate, explicit decision.

## API

JSON endpoints under `/api`, for the dashboard frontend. Full request/response contracts are in [`docs/api.md`](docs/api.md). Every endpoint requires a Laravel Sanctum token (`Authorization: Bearer <token>`). Without one the response is a JSON `401`. Authenticated requests are throttled to 60 per minute per user.

| Endpoint | Roles | Returns |
| --- | --- | --- |
| `GET /api/user` | any | The current user's id, name, email, and role |
| `GET /api/meta/filters` | any | Distinct `status` and `context_id` values present, for filter dropdowns |
| `GET /api/submissions` | editor | Paginated submissions with review assignment counts. Query: `status`, `context_id`, `per_page` (max 100), `page` |
| `GET /api/submissions/{id}` | editor | One submission (local `id`) with its review assignments |
| `GET /api/dashboard/summary` | any | Submission counts by status, total, stuck count, and overdue review count |
| `GET /api/dashboard/stuck` | any | Paginated stuck submissions, plus the threshold used |
| `GET /api/dashboard/overdue-reviews` | any | Paginated overdue review assignments |

Roles:

- `editor` can see every endpoint, including `reviewer_id` on review assignments.
- `viewer` is the default for new users. It can see the dashboard endpoints. `reviewer_id` is left out of its responses, and the raw submission endpoints return `403`.

The schema deliberately stores only the numeric OJS reviewer ID, never reviewer names. Reviewer anonymity matters in peer review, so a viewer being unable to identify reviewers is enforced by the data model, not just by policy. Adding real names would be a deliberate schema change, not a missing feature.

Rules, implemented in `app/Services/Dashboard/EditorialQueries.php`:

- **Stuck:** status `1` (queued, still in the workflow) with `date_last_activity` at least `DASHBOARD_STUCK_DAYS` days old (default 14).
- **Overdue:** `date_due` before today, `date_completed` empty, and not declined or cancelled. A review due today is deliberately not overdue until the day is past: a due date is a deadline, not a violation until it is missed.
- **Status codes:** read from OJS 3.6 source, not checked against a live server.

`php artisan db:seed` creates the local demo users `editor@example.com` and `viewer@example.com` (password `password`). To issue a token:

```sh
php artisan tinker --execute='echo App\Models\User::where("email", "editor@example.com")->first()->createToken("local")->plainTextToken;'
```

## Dashboard

A Vue 3 single-page app served by Laravel at `/`. It reads only from the JSON API above; every number and row on screen comes from an API response.

- **Summary** (default): total submissions, stuck submissions, overdue reviews, and submissions per status, from `GET /api/dashboard/summary`.
- **Stuck submissions**: table from `GET /api/dashboard/stuck` with OJS ID, context ID, status, days since last activity, and review count. Status and context filters are filled from `GET /api/meta/filters`, so they only offer values that exist in the data.
- **Overdue reviews**: table from `GET /api/dashboard/overdue-reviews` with submission, review, round, due date, and days overdue. The reviewer ID column appears only when the response contains `reviewer_id`, so viewers never get one.

The schema stores no submission title and no status-change date, so the tables show the OJS ID and the days since `date_last_activity` instead.

Each view has loading, empty, and error states. A `401` clears the stored token and returns to the sign-in screen with a message.

To run it locally against the mock data:

```sh
php artisan migrate --seed
php artisan ojs:sync
npm run build        # or `npm run dev` while editing the frontend
php artisan serve
```

Create a token for each seeded role and paste one into the sign-in screen. The token is kept in the browser's `localStorage` and sent as `Authorization: Bearer <token>`.

```sh
php artisan tinker --execute='echo App\Models\User::where("email", "editor@example.com")->first()->createToken("dashboard")->plainTextToken;'
php artisan tinker --execute='echo App\Models\User::where("email", "viewer@example.com")->first()->createToken("dashboard")->plainTextToken;'
```

## Observability

Every `ojs:sync` attempt writes a row to `sync_runs`: start and finish time, result, rows upserted, and failures by reason. `SyncRunStarted` and `SyncRunFinished` events fire around it. The dashboard shows the latest row as a status bar, from `GET /api/sync-runs/latest`. Editors also see the first error message; viewers see only the counts.

`GET /metrics` serves Prometheus text format. It needs no token, so restrict it at the network level in any shared deployment.

| Metric | Type | Source |
| --- | --- | --- |
| `ojs_sync_duration_seconds` | histogram | finished `sync_runs`, start to last detail job (includes queue wait and retries) |
| `ojs_sync_total{result}` | counter | `sync_runs` by result (`success`, `failure`) |
| `ojs_upserted_total{entity}` | counter | rows written (`submission`, `review_assignment`) |
| `ojs_sync_failures_total{reason}` | counter | `api_error`, `validation`, `db`, `other`; a detail job counts once, after its retries |
| `ojs_sync_last_success_timestamp` | gauge | Unix time of the last successful run (0 if none) |
| `ojs_submissions_stuck`, `ojs_reviews_overdue` | gauge | the same `EditorialQueries` rules as the API, at scrape time |

The metrics are rebuilt from the database on every scrape instead of being held in process memory. Queue workers and the web server are separate processes, so in-memory counters (APCu) set by a worker would always read zero on `/metrics`. The database is shared and survives restarts. The cost is one scan of `sync_runs` per scrape.

Per-submission failure accounting needs a real queue worker. With `QUEUE_CONNECTION=sync`, the first failing submission aborts the command and the run is recorded as a failure.

### Local stack

```sh
make metrics    # docker compose up -d --build, then prints the URLs
make sync       # run another ojs:sync inside the app container
make down
```

This starts the app on http://localhost:8000 (mock OJS, seeded editor and viewer accounts, one sync on startup), a queue worker, the scheduler, MySQL, Prometheus on http://localhost:9090, and Grafana on http://localhost:3000. Grafana opens with the provisioned "OJS ingestion" dashboard (`deploy/grafana/dashboards/editorial.json`) and needs no login to view.

The Dockerfile and compose file are for local development only. All metrics describe the pipeline running against the bundled mock fixtures, not a live OJS journal.

## Repository layout

- `app/Services/Ojs/` — OJS client (HTTP driver and fixture-backed mock driver)
- `app/Jobs/` — queued OJS ingestion jobs
- `app/Services/Ingestion/` — upsert logic used by the ingestion jobs
- `app/Services/Dashboard/` — stuck and overdue rules used by the API
- `app/Http/Controllers/Api/`, `app/Http/Resources/` — JSON API controllers and response shapes
- `app/Services/Metrics/`, `app/Models/SyncRun.php` — Prometheus exposition and the sync run audit trail
- `deploy/prometheus/`, `deploy/grafana/`, `docker-compose.yml` — local observability stack
- `resources/js/` — Vue dashboard: `api.js` (the only module that calls the API), `lib/` (pure date, filter, and sort helpers), `views/`, `components/`, and Vitest tests in `__tests__/`

## Roadmap / not yet done

- Pruning review assignments that were removed upstream (see Ingestion)
- Batched ingestion jobs instead of one job per submission
- Cookie-based Sanctum SPA authentication instead of pasted tokens
- Publication throughput view (needs publication dates, which are not stored yet)
- Alerting rules (for example on `ojs_sync_last_success_timestamp` staleness)
- Production container image
- Helm chart for Kubernetes / OpenShift
- GitLab CI
- Large synthetic dataset, load tests, and N+1 / index tuning (TODO)
