# Task Management Portal

A small Laravel app for a two-role task workflow: **managers** create tasks
and assign them to **team members**, who work them through a fixed status
lifecycle (`todo → in_progress → under_qa → deploy_to_live → submitted`).
Both a JSON API (`/api/tasks`, token auth via Sanctum) and a session-based
web UI (`/login`, `/dashboard`) sit on the same models/policies/services.

## Setup & run instructions

Requirements: PHP 8.3+, Composer, and a database (MySQL assumed below;
SQLite works too — see note).

```bash
git clone <this-repo> task-management-api
cd task-management-api

composer install

cp .env.example .env
php artisan key:generate

# Configure DB_* in .env (or switch DB_CONNECTION=sqlite for a zero-config
# local run — see "SQLite instead of MySQL" below)

php artisan migrate --seed
php artisan serve
```

### Using the web UI

Visit `http://127.0.0.1:8000/login` and sign in as one of the seeded users
(password `password` for all):

| Email | Role | Notes |
|---|---|---|
| `manager@example.com` | manager | created every seeded task |
| `alice@example.com` | member | assigned 2 tasks, one overdue |
| `bob@example.com` | member | assigned 2 tasks, one already submitted |

A manager sees the tasks they've created and can create/assign new ones; a
member sees only what's assigned to them and can advance its status.

### Using the API

Routes are protected by `auth:sanctum`. There's no `/register` or `/login`
API endpoint — issuing tokens wasn't the focus of this exercise. Issue a
token for any seeded user via Tinker:

```bash
php artisan tinker --execute="echo App\Models\User::where('email','manager@example.com')->first()->createToken('dev')->plainTextToken;"
```

Then call the API at `http://127.0.0.1:8000/api` with
`Authorization: Bearer <token>`.

### SQLite instead of MySQL

To run without configuring MySQL:

```
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

(create the empty file first: `touch database/database.sqlite` — or on
Windows, `New-Item database/database.sqlite`). Tests never touch this —
see below.

## Assumptions I made

The brief's status list and role rules were given informally
("manager … set status to Todo, team member can set status In-progress
… when done submit task … status can be todo, in-progress, Under QA,
Deploy to live, Submitted"), without fully specifying the transition
rules. Where it didn't specify, I made these calls:

1. **The five statuses form one fixed, ordered pipeline**:
   `todo → in_progress → under_qa → deploy_to_live → submitted`. A team
   member can only move a task **one step forward** at a time — no
   skipping stages, no going backward, nothing once it's `submitted`.
   This keeps the workflow meaningful (you can't mark something "deployed"
   without it having gone through QA) and is simple enough to reason
   about and test exhaustively.
2. **The creating manager can override status to anything, at any time**
   (skip stages, reopen a `submitted` task, move it backward). They
   created and own the task end to end, and workflows in practice need an
   escape hatch for mistakes or fast-tracked work. A *different* manager
   has no special access — see point 5.
3. **Status changes go through one dedicated endpoint**
   (`PATCH /tasks/{id}/status`), separate from the general
   `PUT /tasks/{id}`. Rather than splitting "who can change what" logic
   across two endpoints (and risking the rules drifting apart), there's
   exactly one code path — `TaskWorkflowService` — that can ever change a
   task's status, used by both the assignee's forward moves and the
   manager's overrides.
4. **A task must be assigned to a `member` at creation**, and reassignment
   is likewise restricted to `member` users. The workflow only makes sense
   with a single team member driving it forward; assigning a task to a
   manager isn't something the brief describes.
5. **Ownership boundary is the creating manager + the assignee — nobody
   else.** A different manager can't view, edit, or reassign someone
   else's task; an unrelated team member can't touch it either. There's no
   "admin" or "view all" role in this brief, so I didn't invent one.
6. **Definition of "overdue"**: `due_date` is in the past **and** status
   isn't `submitted` yet. Computed on read (`Task::scopeOverdue`), not
   stored, so it can never drift out of sync with the real status.
7. **Timezone for overdue/due-date comparisons**: server/app timezone
   (UTC by default), not a per-user timezone — the API doesn't currently
   capture one. Flagged as a limitation below.
8. **`submitted_at` is derived, not client-settable.** It's stamped
   automatically when status becomes `submitted` and cleared if a manager
   reopens the task, so it can never say something different from what
   the status implies.
9. **Priority has a natural order, not alphabetical.** Sorting by
   `priority` orders `low < medium < high` (via an explicit `CASE`
   expression) — alphabetical order would put "high" before "low", which
   isn't meaningful to a user scanning a task list.
10. **Soft deletes.** Deleting a task marks it deleted rather than
    destroying the row — task history is a reasonable real-world
    requirement for a team-visible portal, and it's a small addition.
11. **Registration/login endpoints are out of scope for the API** — token
    issuance wasn't the point of the exercise. The web UI does have a login
    page, but no self-registration; users are seeded (see table above).

## Key technical decisions

- **Status transition rules live in `TaskWorkflowService`, not the
  controller or a model event.** It's the single place that can move a
  task from one status to another, for both the member's forward-only
  path and the manager's override, so the rules can't drift apart and are
  unit-testable without HTTP or auth (`tests/Unit/TaskWorkflowServiceTest.php`).
  Illegal transitions throw a typed `InvalidStatusTransitionException`,
  which renders as 422 JSON for the API or a flashed error for the web UI.
- **Filtering logic lives in `TaskFilterService`.** Same reasoning as
  above: keeps the controller thin, keeps the filter/sort rules
  unit-testable directly against an Eloquent builder
  (`tests/Unit/TaskFilterServiceTest.php`).
- **Authorization via a Policy (`TaskPolicy`), not manual `if` checks.**
  Six abilities map directly to the rules above: `viewAny`, `view`,
  `create`, `update` (full edit — creating manager only), `updateStatus`
  (assignee or creating manager), `delete` (creating manager only).
  Laravel auto-discovers the policy from the `Task` model name, and both
  the API and web controllers call the same policy.
- **One endpoint per concern.** `PUT /tasks/{id}` only ever changes
  non-status fields; `PATCH /tasks/{id}/status` only ever changes status.
  Splitting them meant each `FormRequest` and policy ability stayed small
  and single-purpose instead of one endpoint branching on role internally.
- **Form Requests for all input validation** (`StoreTaskRequest`,
  `UpdateTaskRequest`, `UpdateTaskStatusRequest`, `FilterTaskRequest`),
  shared as-is between the API and web controllers, including a
  `Rule::exists('users','id')->where('role', 'member')` check so a manager
  literally cannot assign a task to another manager at the validation layer.
- **API Resources for output shaping** (`TaskResource`) so the JSON
  contract is explicit and decoupled from the schema — e.g. `is_overdue`
  is computed and exposed without being a stored column.
- **Composite indexes** on `(user_id, status)` and `(created_by, status)`,
  since every list query filters by assignee-or-creator first and then
  narrows by status; a plain index on `due_date` backs the overdue filter
  and default sort.

## Running the tests

```bash
php artisan test
```

Tests use `RefreshDatabase` against an in-memory SQLite database
(configured in `phpunit.xml`), so they never touch the MySQL/SQLite
database in your `.env` — no extra setup needed beyond `composer install`.

- **`tests/Feature/TaskApiTest.php`** — CRUD endpoints end-to-end: create
  (incl. the "status is always forced to todo" and "can't assign to a
  manager" rules), manager-vs-member scoping on the list endpoint, the
  `assigned_to` filter, status/priority filtering, ownership enforcement
  (403s across managers/members), soft delete, and the unauthenticated path.
- **`tests/Feature/TaskStatusTransitionTest.php`** — the status workflow
  end-to-end: forward-step success, rejecting skips/backward moves/moves
  on a terminal task, rejecting an unrelated user, the manager's override,
  a *different* manager being rejected, and the `submitted_at` stamp/clear
  behaviour.
- **`tests/Unit/TaskFilterServiceTest.php`** — filter/sort logic directly
  against an Eloquent builder, independent of HTTP/auth.
- **`tests/Unit/TaskWorkflowServiceTest.php`** — every transition rule
  directly against the service, independent of HTTP/auth/policies.

## Limitations & what I'd improve with more time

- **No per-user timezone** for due-date/overdue comparisons — a
  `timezone` column on `users` plus timezone-aware comparisons would be
  the correct fix.
- **No registration** — users are seeded rather than self-signed-up; would
  add a registration flow plus rate-limited login attempts in a real
  deployment.
- **No pagination metadata beyond the default Laravel paginator shape** —
  fine for this exercise, but I'd consider a cursor paginator if task
  lists were expected to grow very large.
- **No notifications** on assignment or status change (e.g. notify the
  assignee when a manager assigns them a task, or notify the manager when
  their task reaches `submitted`) — a natural next feature, implemented as
  a queued listener on a `TaskStatusChanged` event rather than inline in
  the service.
- **No bulk operations** (bulk-reassign, bulk status change) — reasonable
  given how task lists get used, but out of scope here.
- **No API versioning** (`/api/v1/...`) — worth adding before this API has
  external consumers.
- **Single flat pipeline, no branching workflow** (e.g. QA rejecting a
  task back to `in_progress` isn't a *team-member* action in this design —
  only the creating manager can move a task backward). If QA rejection
  needs to be a first-class team-member action rather than a manager
  override, that's a small, explicit addition to `TaskWorkflowService`'s
  transition table rather than a redesign.
