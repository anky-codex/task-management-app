# Task Management Portal

A small Laravel app for a two-role task workflow: **managers** create tasks
and assign them to **team members**, who work them through a fixed status
lifecycle (`todo → in_progress → under_qa → deploy_to_live → submitted`).
Both a JSON API (`/api/tasks`, token auth via Sanctum) and a session-based
web UI (`/login`, `/dashboard`) sit on the same models/policies/services.

## Setup & run instructions

1. **Clone and install dependencies**
   
   git clone <this-repo> task-management-app
   cd task-management-app
   composer install 
   (composer install downloads all the code libraries this app depends on.)
   
2. **setup the environment**
  
   cp .env.example .env
   php artisan key:generate
  
  This creates your local settings file and generates a security key the app needs to run.

Next, open the .env file and tell it which database to use. From following options we can follow any of them:

Use MySQL: fill in your database name, username, and password in the DB_* settings.
Or, for the easiest local setup, use SQLite instead: set DB_CONNECTION=sqlite, create an empty file at database/database.sqlite, and point DB_DATABASE to its full path. This needs no separate database server running.


3. **Migrate and seed sample data**
   php artisan migrate --seed
  
4. **Start the app**
   php artisan serve
   (or open it via an existing local vhost, e.g. `http://task-management-app.test`).

### Try it out

Log in at `/login` with any of the seeded users (password `password` for all):

| Email | Role |
|---|---|
| `manager@example.com` | manager |
| `alice@example.com` | member |
| `bob@example.com` | member |

A manager sees the tasks they've created and can create/assign new ones; a
member sees only what's assigned to them and can advance its status.

## Assumptions I made

The instructions I was given described the statuses and roles in a casual, everyday way — things like "manager sets it to Todo," "team member sets it to In-progress," "when done, submit the task" — without spelling out every rule. So wherever something wasn't explicitly stated, here's the judgment call I made and why:

1. **The five statuses form one fixed, ordered pipeline**:
   `todo → in_progress → under_qa → deploy_to_live → submitted`. A team
   member can only move a task **one step forward** at a time — no
   skipping stages, no going backward, nothing once it's `submitted`.
   This keeps the workflow meaningful.
2. **The creating manager can override status to anything, at any time**
   (skip stages, reopen a `submitted` task, move it backward).
3. **Status changes go through one dedicated endpoint**
   (`PATCH /tasks/{id}/status`), separate from the general
   `PUT /tasks/{id}`. Rather than splitting "who can change what" logic
   across two endpoints (and risking the rules drifting apart), there's
   exactly one place in code — `TaskWorkflowService` — that can ever change a
   task's status, used by both the assignee's forward moves and the
   manager's overrides.
4. **A task must be assigned to a `member` at creation**, and reassignment
   is likewise restricted to `member` users.
5. A different manager can't view, edit, or reassign someone
   else's task; an unrelated team member can't touch it either. There's no
   "admin" or "view all" role in this brief.
6. **Definition of "overdue"**: `due_date` is in the past **and** status
   isn't `submitted` yet. 
7. The "submitted" timestamp is set automatically by the system, not typed in by a person. The moment a task's status becomes "submitted," the app stamps the current time on it. If a manager later reopens that task, that timestamp is cleared again — so it can never be out of sync with the task's actual status.
8. **Priority is in form of order, not alphabetical.** Sorting by
   `priority` orders `low < medium < high` (via an explicit `CASE`
   expression) — alphabetical order would put "high" before "low", which
   isn't meaningful to a user scanning a task list.
9. **Soft deletes.** Deleting a task marks it deleted rather than
    destroying the row — task history needs to be shown for future references.


## Key technical decisions
- All the rules about changing a task's status are kept in one place — TaskWorkflowService. Instead of scattering "who can change what status" logic across different files, there's just one file that decides if a status change is allowed. Both a team member moving a task forward and a manager overriding it go through this same file. This means the rules can never accidentally become inconsistent, and I can test all the status rules on their own without needing to fake a full web request or login. If someone tries an illegal move (like skipping a stage), the app throws a clear error that shows up as a proper error message, whether it's the API or the website asking.

- Filtering and sorting tasks is also kept in its own file — TaskFilterService. Same idea as above: it keeps the controller (the file that handles requests) simple, and lets me test the filtering/sorting rules directly and quickly, without needing to spin up the whole app.

- Who's allowed to do what is controlled by a Policy file, not scattered if-checks. Instead of writing "if user is manager, allow this" checks in multiple places, all the permission rules live in one file (TaskPolicy) with six simple abilities: see tasks, view one task, create a task, edit a task (only the manager who made it), change a task's status (the assigned member or that manager), and delete a task (only the manager who made it). Laravel automatically connects this file to the Task feature, and both the website and the API use the exact same rules — so permissions can't drift apart between the two.

- Changing a task's normal details and changing its status are two separate actions. Updating things like the title or description uses one endpoint, and changing the status uses a completely separate one. Keeping them apart made each piece of validation and each permission rule smaller and easier to reason about, instead of one endpoint trying to handle both cases internally.



## Running the tests
To run all the tests, just run:
php artisan test


The tests run against a temporary, in-memory test database (set up in phpunit.xml) — they never touch your real database from .env. So there's no extra setup needed beyond installing the dependencies with composer install.

Here's what's covered:

- tests/Feature/TaskApiTest.php — Tests the whole task API from start to finish: creating tasks (making sure new tasks always start as "todo" and can't be assigned to a manager), making sure managers and members only see the tasks they should, filtering by assigned person/status/priority, blocking people from touching tasks that aren't theirs, soft-deleting tasks, and rejecting requests from people who aren't logged in.

- tests/Feature/TaskStatusTransitionTest.php — Tests the status workflow from start to finish: moving a task one step forward works, but skipping steps, going backward, or changing a finished task doesn't. It also checks that an unrelated user can't change a task's status, that the manager who made a task can override its status, that a different manager cannot, and that the "submitted" timestamp gets set and cleared correctly.

- tests/Unit/TaskFilterServiceTest.php — Tests just the filtering and sorting rules on their own, without going through the website or API at all.

- tests/Unit/TaskWorkflowServiceTest.php — Tests every single status-change rule on its own, without needing logins, permissions, or a web request.


## Limitations & what I'd improve with more time
- Registration — users are seeded rather than self-signed-up; would
  add a registration flow plus rate-limited login attempts in a real
  deployment.
- Notifications on assignment or status change (e.g. notify the
  assignee when a manager assigns them a task, or notify the manager when
  their task reaches `submitted`) — a natural next feature, implemented as
  a queued listener on a `TaskStatusChanged` event rather than inline in
  the service.
- Bulk operations (bulk-reassign, bulk status change) — reasonable
  given how task lists get used, but out of scope here.
- Build API (`/api/v1/...`) — create api for signup, login, task creation, task assignment, and then get on React, Next app/