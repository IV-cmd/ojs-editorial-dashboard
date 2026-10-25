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
