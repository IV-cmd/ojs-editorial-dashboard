# OJS Editorial Dashboard

[![CI](https://github.com/IV-cmd/ojs-editorial-dashboard/actions/workflows/ci.yml/badge.svg)](https://github.com/IV-cmd/ojs-editorial-dashboard/actions/workflows/ci.yml)

A personal learning project to build a dashboard over OJS 3.6 REST API data for editorial teams. The dashboard and OJS integration are planned; this repository currently contains only the initial application scaffold.

## Status

**M0 — scaffold only. No OJS integration, data, or dashboard features exist yet.**

| Target stack | Status in repo |
| --- | --- |
| Laravel 12 / PHP 8.3 | Scaffolded |
| Vue 3 + Vite | Placeholder component |
| MySQL 8 | Configured as the default connection; no application tables yet |
| Pest / Pint / Larastan / Vitest | Configured; CI is on GitHub Actions |
| Docker, Helm, OpenShift, GitLab CI, Prometheus / Grafana | Planned, not started |

### Mock mode (planned)

Later milestones will add a fixture-backed mode so the app can run without an OJS instance. This is not implemented yet.

## Setup

Requirements: PHP 8.3, Composer 2, Node.js 22, and optionally MySQL 8. MySQL is not needed for this scaffold: file-based sessions and cache plus the synchronous queue let the app boot without a database connection.

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

## Repository layout

- `app/Services/Ojs/` — planned OJS client (M1)
- `app/Jobs/` — planned ingestion jobs (M3)
- `deploy/helm/` — planned Helm chart (M7)
- `resources/js/` — Vue scaffold and placeholder component

## Roadmap / not yet done

- OJS 3.6 REST client and fixture mock mode (M1)
- MySQL schema for submissions, reviews, review assignments, and publications; scheduled queue ingestion (M3)
- Internal JSON API and role-based access
- Vue dashboard views for stuck submissions, overdue reviews, and publication throughput
- Prometheus metrics and Grafana
- Dockerfile and Compose setup
- Helm chart for Kubernetes / OpenShift (M7)
- GitLab CI
- Large synthetic dataset, load tests, and N+1 / index tuning (TODO)
