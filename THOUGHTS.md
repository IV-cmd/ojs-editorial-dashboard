# THOUGHTS.md — reasoning log

For each prompt/milestone I record the full thought process: what I expected, what broke,
how I diagnosed it, and why the fix is correct — the "senior colleague thinking out loud"
commentary, not just the end result. Read this alongside the git history.

---

## M0 — Scaffold a Laravel 11 + Vue 3 skeleton with green CI

### What M0 actually is (and why it matters that it's *just* this)
The temptation with a portfolio project is to build everything at once — ingestion, API,
dashboard, Helm — and then spend days untangling why nothing is green. M0 deliberately builds
*nothing functional*. Its only job: a clone that installs, boots, tests, and lints green in CI.
If the foundation isn't provably solid, every later bug is ambiguous ("is it my code or my
setup?"). Getting CI green on an empty skeleton removes that ambiguity forever.

### Decision: boot the app without a database
Laravel 11's default `.env` puts **sessions, cache, and the queue in the database**. That's a
good production default, but it means a fresh clone with no MySQL running can't even render the
welcome page — the first thing a reviewer tries fails. So I set `SESSION_DRIVER=file`,
`CACHE_STORE=file`, `QUEUE_CONNECTION=sync` in `.env.example`, with a comment that the
queue-jobs milestone switches them back to `database`.

Why this is the right call for *now* and not a hack: the drivers are config, not code. Nothing
in the app assumes a driver. When M3 introduces real queued jobs, flipping them back is a
one-line change — and at that point MySQL is genuinely required, so the dependency is honest.
The alternative (telling every reviewer "start MySQL first") adds friction for zero benefit at
a stage where there are no tables anyway.

### Decision: tests run on SQLite in-memory, app defaults to MySQL
Two different needs. The *app* should default to MySQL 8 (`config/database.php` default +
`.env.example`) because that's the real target and I want `php artisan about` to show MySQL.
But *tests* must run with no external service, instantly, in CI. So `phpunit.xml` sets
`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. Laravel ships these lines commented out; I just
uncommented them.

The thing to stay honest about: SQLite and MySQL are **not** the same database. SQLite is
lenient about column types and some SQL that MySQL rejects. For a scaffold with no migrations
this is irrelevant, but once there's a real schema (M3), I want at least one CI job running
Pest against a *real MySQL 8 service container*, otherwise a MySQL-only bug (a strict-mode
rejection, a `DATE_FORMAT` vs `strftime` difference) sails through green CI. Noted for later.

### The CI failure — and why I expected it to pass but it didn't
I ran `php artisan test` and `npm test` locally and both passed, so I pushed expecting green.
The PHP job passed; the **node job failed** at "Run frontend tests":

```
Error: You should not run the Vite HMR server in CI environments.
You should build your assets for production instead.
To disable this ENV check you may set LARAVEL_BYPASS_ENV_CHECK=1
```

#### Diagnosis
Why did it pass locally but fail in CI? The error is thrown by **laravel-vite-plugin**, which
detects a CI environment (via the `CI` env var that GitHub Actions sets but my local shell
didn't) and refuses to start its dev/HMR server. The deeper question: why is Vitest touching
the Laravel Vite plugin at all? Because Vitest, by default, **loads `vite.config.js`** to reuse
your build config — and my `vite.config.js` registered `laravel(...)`. So running the component
test booted the Laravel plugin, which saw `CI=true` and aborted.

This is a classic "works on my machine" trap: the failing condition (`CI=true`) only exists on
the runner. The right way to reproduce it locally is to *recreate the runner's environment*:
`CI=true npm test`. That reproduces the failure on my machine, which is how I confirmed the fix
rather than guessing.

#### The fix and why it's correct (not just a bypass)
The env var says I *could* set `LARAVEL_BYPASS_ENV_CHECK=1`. I deliberately **didn't**. That
flag silences a warning that exists for a real reason (you shouldn't ship an HMR server), and
it treats the symptom. The actual problem is that **the test runner should not be loading the
web-app build config at all** — unit tests for Vue components have nothing to do with how Laravel
serves assets.

So the fix is to separate concerns: a dedicated `vitest.config.js` that loads *only*
`@vitejs/plugin-vue` (needed to compile `.vue` single-file components) plus the jsdom test
environment. `vite.config.js` goes back to importing from `vite` (not `vitest/config`) and
keeps the Laravel plugin for the real build. Now the two toolchains don't touch each other:
the build uses Laravel's plugin, the tests use only what they need. `CI=true npm test` passes,
and `npm run build` still works. That's fixing the cause, not muting the alarm.

### The Vitest security advisory
`npm audit` flagged a **critical** advisory in `tinypool`, pulled in transitively by the Vitest
version I'd installed. Reasoning about severity before acting: these are **devDependencies** —
they run on my laptop and the CI runner, never in production, so the blast radius is small. But
"critical" with an available fix is cheap to clear, so I upgraded to a Vitest release whose
dependency tree no longer includes the vulnerable `tinypool`, confirmed its Vite peer range
still accepts the Vite 6 that Laravel ships, and re-ran the tests. Three *moderate* advisories
remain — all from Tailwind's PostCSS dependencies — which I left alone: no fix is available
without forcing Tailwind versions, and they're dev-only and low-severity. The principle:
clear what's cheap and high-severity, document the rest, don't rabbit-hole on dev-only moderates.

### Open decision carried into M1: Laravel 11 vs 12
`composer audit` reports 5 advisories against `laravel/framework` 11, with fixes only in 12/13.
Laravel 11's security support ended March 2026. For a *scaffold* this is the cheapest moment to
upgrade, but the whole project is explicitly specced as "Laravel 11" (it's the version in the
CERN-style job description being targeted). So this is a judgement call for the repo owner, not
something I'll silently decide. It's on the README roadmap either way.

### Interview-style questions this milestone should leave you able to answer
1. Why does `php artisan test` pass locally but a frontend test fail only in CI — and how would
   you reproduce the CI condition on your laptop before pushing?
2. You set SQLite for tests and MySQL for the app. What class of bug can this hide, and what
   would you add to CI before trusting it with a real schema?
3. Why is separating `vitest.config.js` from `vite.config.js` a better fix than
   `LARAVEL_BYPASS_ENV_CHECK=1`? What does each actually change?

---

## M0 follow-up — upgrading the scaffold from Laravel 11 to Laravel 12

### Why do this now, and why it was my question to ask rather than my call to make
`composer audit` on the fresh scaffold reported 5 advisories against `laravel/framework` 11 —
including a *critical* TokenGuard authentication-bypass — all fixed only in 12.x/13.x, because
Laravel 11's security support ended March 2026. Two competing facts: (a) the project is specced
as "Laravel 11" to mirror the target job description, and (b) staying on an EOL framework with a
known critical advisory is a bad look for a *security-adjacent* portfolio piece.

The reason I surfaced it instead of silently upgrading: changing the headline framework version
is a spec decision, not a mechanical fix. The owner might have a reason to match "11" exactly.
When a change contradicts the written spec, the right move is to present the tradeoff and let the
owner decide — not to quietly override. The call was to move to 12.

### Why a scaffold is the cheapest possible moment to do a major upgrade
A framework major upgrade's cost scales with how much of *your* code touches the parts that
changed. On a scaffold there's almost no app code — no models, no controllers, no middleware of
ours — so the surface area for breakage is near zero. The same upgrade after M3 (ingestion jobs,
migrations, API) would mean auditing every one of those against the upgrade guide. Doing it now
converts a potentially painful future migration into a near-free version bump. "Upgrade early
when the blast radius is smallest" is the general principle.

### What actually changed, and what pleasantly didn't
Laravel 12 is deliberately a low-breaking-change release. In practice: `composer.json` moved to
`^12.0`, `composer update` resolved `laravel/framework` to 12.70.1 and pulled one new polyfill
(`symfony/polyfill-php84`), and **no app-code changes were required** — the scaffold doesn't call
any of the APIs the upgrade guide flags. All gates stayed green (Pint, PHPStan level 5 with no
baseline, Pest, Vitest, Vite build) with no new fixes. The payoff is concrete: `composer audit`
went from 5 advisories (1 critical) to **zero**.

The lesson worth internalising: a clean upgrade is *evidence the scaffold was healthy*, not luck.
Because there was no app code reaching into framework internals, there was nothing to break. The
more your code couples to a framework's internals, the more a major bump hurts — which is an
argument for leaning on documented, stable APIs over clever internals.

---

## M1 — the OJS client + mock/fixture mode

### The problem this milestone actually solves
The whole dashboard reads from a live OJS 3.6 server. If every layer above it assumed a real
server, nobody could run or test the app without one — and OJS is a heavy thing to stand up. So
M1's real job isn't "write an HTTP client," it's "make the server a *swappable edge* so the other
95% of the app never knows or cares whether the data came over the wire or from a JSON file on
disk." That's why the interface comes first and the two drivers sit behind it.

### Why the interface is two methods, not the spec's `getReviewAssignments()`
The spec asked for `getSubmissions()` + `getReviewAssignments()`. Reading pkp-lib/ojs source
(version.xml 3.6.0.0 — *not* live-tested, and the code says so) showed the list endpoint returns
**summaries only**; review assignments exist solely on the `GET /submissions/{id}` detail object.
There is no "list all assignments across the journal" endpoint in 3.6. So a clean
`getReviewAssignments()` would have to secretly do list → N× detail → flatten. That's a *policy*
(an N+1 trade-off, rate-limit strategy, caching) and the client's job is transport, not policy.
The interface therefore mirrors the API exactly — `getSubmissions()` (summaries) +
`getSubmission(int $id)` (detail, which *contains* the assignments) — and leaves the flatten
decision to M3 ingestion where it belongs. The general rule: **the transport layer should express
what the API actually offers, not the convenience shape a caller wishes it had.**

### Why the fixtures are two genuinely different shapes
The easy, wrong move is to author one rich submission object and return it from both the list and
the detail. That makes the mock *lie*: tests would pass with `reviewAssignments` present in the
list, then M3 would break on a real server where the list never carries them. So the summary
fixture (`submissions.json`) deliberately omits `reviewAssignments`, and only the per-id detail
files (`submissions/{id}.json`) carry the nested `reviewRounds[]`/`reviewAssignments[]`. A test
asserts the summary has *no* `reviewAssignments` key. A mock is only useful if it lies in exactly
zero ways that matter — otherwise it's a trap that passes CI and fails production.

### Where pagination lives, and why the mock doesn't fake it
OJS caps `count` at 100 and returns `{itemsMax, items}`. Pagination is an HTTP-transport detail,
so it lives *inside* `HttpOjsClient` — not in the interface (callers say "give me all
submissions," not "give me page 3") and not in a shared base class (there's one real paginator;
a base class would be inheritance for its own sake). The loop advances the offset by the number
of items actually returned and stops when the accumulated count reaches `itemsMax` or a page comes
back empty — advancing by *items returned* rather than a blind `+100` is self-correcting if a page
is short. `MockOjsClient` returns the whole fixture in one shot and does **not** pretend to
paginate: faking pagination over static files would be theatre that tests nothing real. Pagination
is proven instead with `Http::fake()` returning two pages.

### Why one typed exception, and the token-redaction trap
Callers should never see Guzzle/Laravel HTTP types leak out of the client, so every transport or
non-2xx failure is wrapped in a single `OjsApiException` carrying the HTTP status and a truncated
(500-char) body snippet. The subtle hazard: the thing that authenticates us — the bearer token —
must never end up in an exception message or a log. Two defences: we never put request *headers*
into the exception (only the response body), and as a belt-and-braces measure the client
string-replaces the token with `[redacted]` in any body before constructing the exception. A test
feeds a response body containing the token and asserts the message does **not** contain it. The
principle: **the error path is still a code path — secrets can leak through a stack trace as
easily as through a log line.**

### Why the driver defaults to `mock`
`config('ojs.driver')` defaults to `mock` and `phpunit.xml` pins `OJS_DRIVER=mock`. A fresh clone
with no `.env` edits therefore boots, serves, and runs the full test suite with **zero external
dependencies** — no OJS, no token, no network. That's the entire point of M1: a contributor (or
CI) proves the app works before anyone hands them a real OJS instance. Defaulting to `http` would
make a blank checkout fail on first run, which is exactly the "works on my machine" failure M0's
reproducibility work was fighting. The safe default is the one that needs the least to be true.

### What stayed out (scope discipline)
No migrations, no persistence, no queue jobs, no DTOs, no UI. Return shapes are plain decoded
arrays. DTOs are tempting but premature — their right shape is driven by what *persistence* needs,
and persistence is M2. Building them now would be guessing. The client is pure fetch-and-decode;
every decision about *what to do* with the data is deferred to the milestone that has the context
to make it.

### The guard caught CI before it caught production (a good surprise)
Right after adding the "refuse to boot on mock-in-production" guard, the PHP CI job went red —
during `composer install`, of all steps:

```
> @php artisan package:discover --ansi
OJS driver is set to "mock" in production; refusing to boot and serve fabricated data.
```

This is the guard working *too well*, and it taught a real Laravel fact. Composer's
post-autoload-dump hook runs `artisan package:discover`, which **boots the framework**. CI runs
that before any `.env` is copied, so `APP_ENV` is unset — and Laravel defaults an unconfigured
environment to **`production`**. Meanwhile the OJS driver defaults to `mock`. Production + mock is
exactly the combination the guard exists to reject, so it fired on a build machine instead of a
real deploy.

The fix is to tell CI the truth about what it is: `env: { APP_ENV: testing }` on the PHP job only.
The mock default and the guard are both left untouched — the guard's logic was never wrong, the
environment label was. The lesson worth keeping: **"no config" does not mean "safe/dev" to Laravel
— it means `production`.** Any guard keyed on the production environment will also judge every
unconfigured context (CI, a bare `artisan` call, a container with no env) as production, so those
contexts must declare their environment explicitly rather than relying on a benign-looking default.

---

## M2 — the MySQL persistence layer

### The problem this milestone solves (and what it deliberately doesn't)
M1 gave us a client that returns OJS data as plain arrays. But an array vanishes the moment the
request ends — there's nowhere for ingested data to *live*, nothing to query for "stuck
submissions," no way for M3 to run twice without duplicating everything. M2 is purely about
*shape and place*: where ingested rows land, how they're typed, and — the load-bearing part — what
makes a row's identity unique so M3 can re-run safely. M2 intentionally does **not** fetch or
populate anything from the client; wiring the client to the tables is M3. Keeping "define the
schema" and "fill the schema" in separate milestones means each is reviewable on its own.

### Why `ojs_id` is a column, not the primary key
The tempting shortcut is to use OJS's submission id as our primary key. It's wrong for two reasons.
First, it couples our local identity to a foreign system — if we ever ingest from a second OJS
context, or OJS reissues ids, our PK space collides. Second, foreign keys and relationships should
point at *our* stable local id, not a number we don't control. So every table keeps a local
auto-increment `id` as PK, and the OJS id lives in a separate `ojs_id` column. The relationship
(`review_assignments.submission_id`) references the local `submissions.id`, never the OJS id.

### The one decision the whole milestone rests on: the UNIQUE key
M3 will ingest on a schedule and must be *idempotent* — running it twice must not create two copies
of the same submission. The clean way to guarantee that is `updateOrCreate`/`upsert` keyed on a
natural key, which only works if the database *enforces* that the key is unique. So M2 adds
`UNIQUE(context_id, ojs_id)` on submissions and `UNIQUE(ojs_id)` on review assignments now, before
any ingestion exists. The composite on submissions is because an OJS submission id is only unique
*within a journal/context*; a review-assignment id is globally unique in OJS 3.6, so a single-column
unique is enough and a composite would be redundant. Enforcing uniqueness at the schema level, not
in application code, is what makes the idempotency guarantee real rather than hopeful — the database
rejects the duplicate even if a bug in the ingestion logic tries to write it.

### Why the tests had to move off SQLite onto MySQL 8 (the biggest change here)
M0/M1 tested on SQLite in-memory because it's instant and needs no server. That was fine while the
tests exercised no real constraints. M2 breaks that: the entire point is a UNIQUE constraint, and
**SQLite and MySQL do not enforce constraints identically** (type affinity, how violations surface,
index semantics all differ). A duplicate-insert test that passes on SQLite would be testing a
different engine than the one production and M3 rely on — a green test that proves nothing. So the
suite now runs on MySQL 8: `phpunit.xml` switches to the `mysql` connection, and the CI PHP job
gains a `mysql:8` service container (with a health-check so tests wait until it's ready) and swaps
the `pdo_sqlite` extension for `pdo_mysql`. The duplicate-key tests even assert
`DB::connection()->getDriverName() === 'mysql'` first, so they can't silently pass on the wrong
engine. The cost is that running the suite now requires a MySQL instance (locally: a docker
container); that's the right trade — a test is only worth running if it tests the real thing.

### Casts, immutability, and the UTC assumption
Dates cast to `immutable_datetime` (CarbonImmutable) rather than mutable Carbon, so a date read
from a model can't be accidentally mutated in place by a caller — a small correctness guard that
costs nothing. Booleans (`declined`, `cancelled`) cast to real `bool` so query code never has to
remember they're `1`/`0` in the column. OJS stores timestamps in UTC (read from 3.6 source, stated
in the migration comment as *not* verified against a live server), and the app runs UTC, so values
are stored as-is with no conversion — a conversion we don't need is a bug we can't introduce.

### Indexes: justified, not speculative
Indexes on `status`, `stage_id`, `date_last_activity` (submissions) and `date_due`,
`date_completed` (review assignments) each map to a concrete M4 query path (stuck submissions,
overdue reviews). I deliberately did **not** index the `declined`/`cancelled` booleans: a
two-value column has terrible selectivity, so a standalone index on it rarely helps and still costs
on every write. If M4's measured query plans want it, a *composite* index combining a boolean with
`date_due` is the better tool — that's a roadmap item, not a guess made now. The general rule: an
index is a bet that a specific query will run often; add it when you know the query, not before.

### What M2 left out on purpose
No `publications` table. The only thing the dashboard needs from publications is
`current_publication_id`, which is a plain nullable column on `submissions`; none of M4's three
queries (stuck, overdue, throughput) need publication *rows*. Building a speculative table "because
OJS has one" would be code with no caller — deferred to a roadmap line instead. Also no ingestion,
no API, no UI — those are M3/M4/M5.

## Ingestion — a queued sync that is safe to re-run

### The problem
The dashboard should read local MySQL, not OJS, so something has to copy OJS data in on a
timetable. That copy runs again and again over the same records, and the queue can deliver the
same job twice (timeouts, worker crashes, retries). So "it ran twice" is normal, not an edge case.
The real requirement is that a second run changes nothing it shouldn't.

### Upsert logic kept apart from job plumbing
`OjsUpserter` takes a decoded detail array and writes rows. It makes no HTTP calls and knows
nothing about queues, so the tests call it directly with plain arrays. The jobs only move data
around: `SyncSubmissions` lists summaries and dispatches work, and `IngestSubmission` fetches one
detail and passes it to the upserter.

### Why the natural keys, and why the schema still matters
Every write is `updateOrCreate` matched on `(context_id, ojs_id)` or the assignment `ojs_id`,
never a plain `create()`. The application-level "find then write" isn't atomic by itself: two
overlapping runs could both miss the row and both insert it. The unique keys in the schema are
what reject the second insert, so the upsert relies on them.

### One job per submission, carrying only the ID
Fanning out one job per submission means one bad or slow submission retries alone instead of
re-running the whole sync. The job carries the OJS ID, not the payload, so a retry re-fetches
current data instead of replaying a stale snapshot. `$tries = 3` with backoff 10/30/60s gives
OJS time to recover. Batching would cut job overhead at scale and stays on the roadmap until
there's a measured reason to add it.

### One transaction per submission
The submission and its review assignments are written in one `DB::transaction`. If any
assignment fails, the whole submission rolls back, so there's never a submission with half its
assignments. The rollback test passes an assignment with no ID (which violates NOT NULL) and
asserts that neither the submission nor its valid sibling assignment was kept.

### Upsert-only, on purpose
Assignments removed upstream are not pruned. Deleting everything and reinserting on each run
would look tidy, but it changes local row IDs every run and throws away the last known state.
A pruning step can be added later as an explicit decision.

### What I verified, and how
The full sync runs twice in Pest on MySQL 8 (sync queue, mock driver), and the test checks that
row counts and local IDs are identical. Outside the tests I ran `ojs:sync` against MySQL with the
database queue and `queue:work --stop-when-empty`, twice: 5 submissions and 3 assignments, the
same IDs both times, 0 failed jobs. Not verified: anything against a live OJS server.
