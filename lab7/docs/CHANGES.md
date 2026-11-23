# Lab 7 change log (Lab 6 → Lab 7)

## How the three guidance documents were applied

| Document | How it shaped the work |
|---|---|
| **Application Builder** | Kept the existing structure and naming (`php/` pages → `services/` → `repositories/` → SQLite); SQL only in repositories, rules and transactions only in services, HTML only in pages; prepared statements; `escapeHtml` on output; POST for every change; foreign keys on; PHP-version-safe syntax; every changed file linted. Its "do not add tables / authentication" boundary was a Week 7 rule: this assignment explicitly asks for authentication, and Handout §2 specifies the `users` table and `students.user_id`, so those are treated as the approval. `tutoring_requests` is untouched. |
| **Test Harness Generator** | Tests call the real functions, use `sqlite::memory:` built from `setup-database.sql`, print PASS/FAIL only after evaluating a condition, count results, exit non-zero on any failure or crash, include a negative control, and never open `tutoring.db`. |
| **Traceability Auditor** | `docs/TRACEABILITY.md` follows its matrix, status labels (Verified only with executed evidence), forward/backward trace and findings format. Requirement IDs are the design document's own (UM-01 … UM-10). |
| **Authentication Handout** | Sections 2–19 are followed as written; the few deliberate deviations are listed at the end of this file. |

## New files

| File | Purpose |
|---|---|
| `includes/auth.php` | Secure session start, `establishSession`, `logoutSession`, `evaluateSession`, `requireLogin`, `requireRole`, CSRF helpers |
| `includes/helpers.php`, `includes/errors.php` | `escapeHtml` (moved here from `requestService.php`), redirects, flash messages, error pages, exception types |
| `repositories/userPDO.php` | All SQL for the `users` table |
| `services/userService.php` | UM-01 … UM-09 business rules |
| `php/register.php`, `login.php`, `logout.php` | UM-01, UM-09, UM-10 |
| `php/profile.php`, `edit-profile.php`, `change-password.php` | UM-02, UM-04, UM-05 |
| `php/users.php`, `user-details.php`, `user-role.php`, `user-status.php`, `user-delete.php` | UM-03, UM-06, UM-07, UM-08 |
| `database/setup-database.php` | CLI: create/upgrade the database, upgrade a Lab 6 database in place, seed demo accounts |
| `database/upgrade-to-user-management.sql` | SQL-only upgrade of a Lab 6 database (adds `users`, `students.user_id`; keeps all data) |
| `database/create-admin.php` | CLI to create the first Administrator (`php create-admin.php "Name" email "Password"`; prompts or `CREATE_ADMIN_PASSWORD` when the password is omitted) |
| `lab7-notes.md` | Lab notes: prompt, source files, how Lab 7 extends Lab 6, one verified example |
| `tests/test-helpers.php`, `user-management-tests.php`, `database-scripts-tests.php`, `run-all.php`, `e2e-http-tests.sh` | Test harness and suites |
| `docs/TRACEABILITY.md`, `docs/CHANGES.md`, `docs/test-results.txt` | Audit, this file, recorded test output |

## Modified files

| File | Change |
|---|---|
| `database/setup-database.sql` | Lab 6 structure only (`students`, `tutoring_requests`), **no seed rows** (see "Lab 6 baseline" below). Run first; `upgrade-to-user-management.sql` then adds `users` and `students.user_id`, as the lab's Part 2 expects. |
| `database/tutoring.db` | Not shipped: students create it in lab Part 2. |
| `config/db.php` | Optional `TUTORING_DB` environment variable so tests can use a temp database. Default path unchanged. |
| `includes/validation.php` | Lab 4 validators untouched; user-management validators and role/status constants appended. |
| `includes/nav.php` | Role-aware links, POST logout button, flash messages. |
| `repositories/studentPDO.php` | `findStudentByEmail` kept; `insertStudentIfAbsent` replaced by `insertStudentForUser`; added `findStudentByUserId`, `syncStudentProfile`, `deleteEmptyStudentForUser`. |
| `repositories/requestPDO.php` | Added `findRequestForUser`; `listRequests` gets an optional owner filter; `updateOpenRequest` / `cancelOpenRequest` take a required `$ownerUserId` (null = administrator). |
| `services/requestService.php` | `submitRequest($pdo, $input, $actor)` (Student only; name/email taken from the account; student row created/linked in the same transaction). `reviseRequest($pdo, $id, $input, $actor)`. New `cancelRequest`, `getRequestForActor`, `listRequestsForActor`, `requestOwnerScope`. Validation, JSON topic convention and `decodeTopic` unchanged. |
| `php/request-form.php`, `process-request.php`, `requests.php`, `request-details.php`, `edit-request.php`, `update-request.php`, `delete-request.php` | Login guard, role check, CSRF token on every form/POST, ownership via the service. Lab 4 controls (course dropdown, topic checkboxes, date, time radios, reason textarea), URLs and HTTP status codes preserved. Name and email fields are now read-only and filled from the account. |
| `php/router.php` | Allow-list instead of block-list (see README). |
| `index.php` | Redirects to the profile page (which sends visitors who are not signed in to the login page). |
| `css/styles.css` | Lab 6 rules untouched; Lab 7 rules appended at the end. |
| `tests/integration-tests.php` | Lab 6's 14 checks kept (adapted to the new signatures) and extended to 35 UC01/UC02 checks (adds transaction rollback, special characters, more invalid inputs), plus 32 access-matrix, ownership and schema checks. |
| `README.md` | Rewritten for Lab 7. |

Unchanged: `includes/header.php`, `includes/footer.php`, `tests/validation-tests.php`.

## Lab 6 baseline (found before changing anything)

Running Lab 6's own `tests/integration-tests.php` as delivered gave **3 failing checks** (duplicate-email reuse, UC02 course+status filter,
"invalid submission writes nothing"). The cause was the seed rows in `setup-database.sql`, which the tests load but their expected counts
did not allow for. Lab 7 removes seed rows from the schema file (demo data now comes from `setup-database.php --demo`) and each test builds
its own fixtures, so the same checks now pass. This is a test/fixture fix, not a change to UC01/UC02 behaviour.

## Where Lab 7 deliberately differs from the Handout's snippets

1. `submitRequest` takes the authenticated user as a third argument. The handout's `submitRequest($pdo, $_POST)` has no way to link the new request to `students.user_id`.
2. `process-request.php` checks **login → role → CSRF** (the handout shows CSRF first). With an expired session the user is sent to the login page instead of seeing a 403.
3. `findUserById` does not select `password_hash`; only `findUserByEmail` (login) and `findUserAuthById` (change password) do, so ordinary reads cannot leak it.
4. Login and registration forms also carry CSRF tokens, and the session cookie honours `X-Forwarded-Proto` so `Secure` is set behind the Codespaces proxy.
5. Ownership failures return **404**, role failures **403** (handout §11, §14).
6. The handout's table in §13 repeats the "Manage users and roles" row; it was treated as one rule.

## Requirement baseline note

`TUTORING_REQUESTS.user_id (proposed)` in the design's conceptual model is reached through `students.user_id`, as the handout's schema requires.
The design says to "reconcile before implementation"; this is that reconciliation.
