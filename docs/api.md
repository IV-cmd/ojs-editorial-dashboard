# API reference

All endpoints live under `/api` and return JSON. Authenticate with a Laravel Sanctum token in the `Authorization: Bearer <token>` header. A missing or invalid token gives `401`; a valid token without the required role gives `403`. Every response is JSON, including errors.

Authenticated requests are limited to 60 per minute per user; exceeding it returns `429` with `Retry-After`.

Roles: `editor` sees everything, including `reviewer_id` on review assignments. `viewer` (the default for a new user) sees only the `dashboard` and `meta` endpoints, and `reviewer_id` is omitted from its payloads entirely rather than sent as `null`.

Dates are ISO 8601 strings or `null`. The examples below are real responses captured from a local run against the bundled synthetic fixtures (`php artisan ojs:sync`, editor token). The fixtures carry no reviewer IDs, so `reviewer_id: 77` on assignment 501 was set by hand for the capture. Paginator `links`/`meta.links` are trimmed. List endpoints use Laravel's paginator and accept `per_page` (1–100, default from `DASHBOARD_PER_PAGE`) and `page`.

## `GET /api/user`

Roles: any. The token owner.

```json
{"id":1,"name":"Demo Editor","email":"editor@example.com","role":"editor"}
```

## `GET /api/meta/filters`

Roles: any. Distinct values present in the data, for building filter dropdowns.

```json
{"data":{"statuses":[1,3,4],"context_ids":[1]}}
```

## `GET /api/submissions`

Roles: editor. Paginated submissions with `review_assignments_count`. Query: `status` (int), `context_id` (int), `per_page`, `page`. Invalid values give `422`.

`GET /api/submissions?per_page=1`:

```json
{
  "data": [
    {"id":1,"ojs_id":101,"context_id":1,"status":1,"stage_id":1,"submission_progress":"","current_publication_id":null,
     "date_submitted":"2022-01-03T09:00:00+00:00","date_last_activity":"2022-02-01T09:00:00+00:00",
     "last_modified":"2022-02-01T09:00:00+00:00","review_assignments_count":0}
  ],
  "links": {"first":"…?per_page=1&page=1","last":"…?per_page=1&page=5","prev":null,"next":"…?per_page=1&page=2"},
  "meta": {"current_page":1,"from":1,"last_page":5,"path":"…/api/submissions","per_page":1,"to":1,"total":5}
}
```

## `GET /api/submissions/{id}`

Roles: editor. One submission by local `id`, with `review_assignments` (no count field). Unknown id gives `404`.

`GET /api/submissions/5` (first of its three assignments shown):

```json
{"data":{"id":5,"ojs_id":105,"context_id":1,"status":1,"stage_id":3,"submission_progress":"","current_publication_id":null,
  "date_submitted":"2026-08-03T09:00:00+00:00","date_last_activity":"2026-10-20T09:00:00+00:00","last_modified":"2026-10-20T09:00:00+00:00",
  "review_assignments":[
    {"id":1,"ojs_id":501,"submission_id":5,"reviewer_id":77,"round":1,"review_round_id":1,"status":6,
     "date_assigned":"2001-01-01T09:00:00+00:00","date_due":"2001-01-15T09:00:00+00:00","date_response_due":"2001-01-10T09:00:00+00:00",
     "date_confirmed":null,"date_completed":null,"declined":false,"cancelled":false}
  ]}}
```

For a `viewer`, the `reviewer_id` key is absent from every review assignment (on the endpoints a viewer can reach).

## `GET /api/dashboard/summary`

Roles: any. Header counts, from real queries.

```json
{"data":{"submissions_total":5,"submissions_by_status":[{"status":1,"count":3},{"status":3,"count":1},{"status":4,"count":1}],
  "stuck_count":1,"overdue_reviews_count":2},
 "meta":{"as_of":"2026-10-27T05:13:47+00:00","stuck_threshold_days":14}}
```

## `GET /api/dashboard/stuck`

Roles: any. Paginated stuck submissions, same item shape as `/api/submissions`. `meta` adds `as_of` and `stuck_threshold_days` to the paginator fields.

Stuck: status `1` (queued) with `date_last_activity` at least `DASHBOARD_STUCK_DAYS` days before `as_of`. Exactly at the threshold counts; a submission with no activity date does not.

```json
{"data":[{"id":1,"ojs_id":101,"context_id":1,"status":1,"stage_id":1,"…":"…","review_assignments_count":0}],
 "meta":{"current_page":1,"last_page":1,"per_page":1,"total":1,"as_of":"2026-10-27T05:13:47+00:00","stuck_threshold_days":14}}
```

## `GET /api/dashboard/overdue-reviews`

Roles: any. Paginated overdue review assignments, with `submission_ojs_id` added. `meta` adds `as_of`.

Overdue: `date_due` before the day of `as_of`, `date_completed` empty, and not declined or cancelled. A review due today is not overdue until the day is past; one completed after its due date is not overdue.

```json
{"data":[{"id":1,"ojs_id":501,"submission_id":5,"submission_ojs_id":105,"reviewer_id":77,"round":1,"review_round_id":1,"status":6,
  "date_assigned":"2001-01-01T09:00:00+00:00","date_due":"2001-01-15T09:00:00+00:00","date_response_due":"2001-01-10T09:00:00+00:00",
  "date_confirmed":null,"date_completed":null,"declined":false,"cancelled":false}],
 "meta":{"current_page":1,"last_page":2,"per_page":1,"total":2,"as_of":"2026-10-27T05:13:47+00:00"}}
```
