# Requirements Traceability Audit — Peer Tutoring Hub Lab 7 (UM-01 … UM-10)

Method: *Peer Tutoring Hub Requirements Traceability Auditor*. A status of **Verified** is used only where tests were
actually executed and their output is recorded in `docs/test-results.txt`; a test file existing is never treated as evidence.

## 1. Scope and inspected materials

| Item | Used as |
|---|---|
| *User Management Design Document v1.0* (use cases UM-01 … UM-10, WF-01 … WF-08, USER data dictionary, business rules, acceptance tests) | Requirements baseline. IDs are the document's own; business rules have no IDs in the document and are quoted. |
| *IS 6465 Authentication Reading Handout v1.2* | Security and access rules, schema (`users`, `students.user_id`), access matrix §13 |
| Lab 6 application (`lab6.zip`) | Existing code audited and extended |
| Application Builder / Test Harness Generator / Traceability Auditor | Method |
| Executed evidence | `docs/test-results.txt` (lint 39/39 files; CLI 124 + 67 checks; HTTP 221 checks; negative control exits 1) |

Not supplied / not inspected: an approved deletion/retention policy, final password rules, any Week 6 ERD beyond the handout's schema, pre-existing Lab 6 UC01/UC02 requirement IDs
(the Lab 6 behaviour was preserved and is referred to as "UC01/UC02" as in the Lab 6 tests).

## 2. Overall assessment

All ten use cases are implemented end to end (wireframe → page → service → repository → table) and every acceptance test in the design is
backed by executed evidence. **Nine are Verified; UM-08 is Partial** because the design leaves the deletion/retention policy
unresolved — the mechanism is verified, but the policy it enforces is my conservative default, not an approved requirement. Three open decisions need
sign-off (see §8). No finding prevents classroom use; the security limitations in §6 matter before any wider deployment.

## 3. Forward traceability matrix

Layer abbreviations: **P** = page in `php/`, **S** = `services/userService.php`, **R** = `repositories/userPDO.php` (or as noted), **T** = table.
CLI tests are in `tests/user-management-tests.php` / `tests/integration-tests.php` (IDs below); HTTP tests are `E2E-nn` sections in `tests/e2e-http-tests.sh`.

| Requirement ID | Requirement | Implementation evidence | Test evidence (executed) | Status | Gap or action |
|---|---|---|---|---|---|
| **UM-01** (WF-01) | Student/Tutor create an Active account: validate fields, unique email, password match, allowed role; store hash; confirm | P `register.php` (form with name/email/password/confirm/Student-Tutor radios) → S `registerUser` (role check first, `validate*`, duplicate check + catch of UNIQUE race, `password_hash(PASSWORD_DEFAULT)`, status `Active`) → R `insertUser` → T `users`. Confirmation via redirect to `login.php?registered=1` | UM-01-T01…T12 (Student, Tutor, hash-only storage, no confirm-password column, duplicate incl. case, Administrator denied, bad role/array role, 6 invalid-field cases, active immediately, SQL metacharacters); E2E-09 | **Verified** | Password rules are a default (open decision 2) |
| **UM-02** (WF-02) | Signed-in user views own name, email, role, status; expired session is a failure | P `profile.php` (no id parameter) → S `getOwnProfile` → R `findUserById` (no `password_hash`) ; guard `requireLogin` / `evaluateSession` | UM-02-T01…T07 (own data, no hash, two users, expiry boundary 1200 s/1201 s, anonymous); E2E-06 (`?user_id=1` still shows own), E2E-01, E2E-16 | **Verified** | — |
| **UM-03** (WF-03) | Administrator lists, searches, filters and opens accounts; unauthorized or no match is a failure | P `users.php`, `user-details.php` (role gate `requireRole`) → S `searchUserAccounts`, `getUserForAdministrator` (re-checks admin) → R `searchUsers` (LIKE with escaped wildcards), `countRequestsForUser` | UM-03-T01…T16 (all, name/email text, role, status, combined, none, `%`/`_` literal, Student/Tutor denied, invalid filters, no hash, details + request count); E2E-07, E2E-08 | **Verified** | No pagination (see §6) |
| **UM-04** (WF-02) | Edit own name/email; validate ownership and fields; duplicate email fails; role/status cannot change | P `edit-profile.php` → S `updateOwnProfile` (rejects `role`/`status` keys with 403, acts only on the session user, transaction incl. `syncStudentProfile`) → R `updateUserProfile` | UM-04-T01…T10 (update, normalisation, role/status untouched, other user untouched, duplicate, own-email no-op, invalid, forged role/status → nothing changes, forged `user_id` ignored, student row sync); E2E-10 | **Verified** | — |
| **UM-05** (WF-04) | Verify current password, validate new one, store new hash; wrong current or mismatch fails | P `change-password.php` (regenerates session ID after success) → S `changeOwnPassword` → R `findUserAuthById`, `updateUserPassword` | UM-05-T01…T09 (wrong current, mismatch, weak, old still works after failures, success, new works, **old fails**, hash changed & not plaintext, others untouched); E2E-11 | **Verified** | — |
| **UM-06** (WF-05) | Administrator changes a role; validate authorization and role rules; unauthorized/prohibited change fails | P `user-role.php` → S `changeUserRole` (admin check, role whitelist, no-op rejected) → R `updateUserRoleGuarded` (atomic last-active-administrator guard) | UM-06-T01…T11 (Student→Tutor, promote, Student/Tutor actors and self-promotion denied, unknown role, missing, unchanged, **last admin protected**, inactive admin not a replacement, allowed with 2nd admin, DB role beats stale session role); E2E-07, E2E-13 | **Verified** | — |
| **UM-07** (WF-06) | Administrator activates/deactivates with confirmation; inactive users cannot log in; invalid transition fails | P `user-status.php` (GET = confirmation screen, POST = change) → S `setUserStatus` → R `updateUserStatusGuarded`; session effect via `evaluateSession` | UM-07-T01…T11 (deactivate, **login refused**, open session ended, others unaffected, reactivate, non-admin denied, unknown status, same-state, missing, last admin); E2E-14 (live session is signed out on its next request) | **Verified** | — |
| **UM-08** (WF-07) | Administrator deletes an account under an approved retention policy: warn, recheck authorization and relationships, delete only if permitted, otherwise explain the block | P `user-delete.php` (warning with related-request count; no confirm button when blocked) → S `previewAccountDeletion`, `deleteUserAccount` (transaction, re-check, 409 with reason) → R `deleteUserGuarded`, `studentPDO::deleteEmptyStudentForUser`, `countRequestsForUser`; FK `students.user_id`/`student_id` as backstop | UM-08-T01…T14 (preview, blocked by requests incl. Cancelled, **nothing changes when blocked**, non-admin denied, missing, allowed delete removes only user + empty student row, last admin, 2nd admin, FK refuses orphaning); E2E-15, E2E-18 | **Partial** | The design says the policy is *unresolved* ("Deletion policy unresolved"; precondition "approved policy"). Implemented default: any related request blocks deletion. Mechanism Verified; **policy needs approval** (open decision 1) |
| **UM-09** (WF-08) | Verify hash and status; regenerate session ID; establish session; invalid credentials or inactive account fail | P `login.php` → S `authenticateUser` (prepared lookup, `password_verify`, dummy hash for unknown email, `password_needs_rehash`) → `establishSession` (`session_regenerate_id(true)`, minimal session data, fresh CSRF token) | UM-09-T01…T12 (valid, wrong, unknown, inactive, case/space, empty, case-sensitive password, dummy hash valid, rehash, session resolves/deleted user); E2E-03 (cookie flags), E2E-04 (generic message identical for 3 failure kinds, CSRF), E2E-05 (**session ID changes**, attacker-chosen ID refused) | **Verified** | No brute-force limit (see §6) |
| **UM-10** (navigation) | Logout invalidates session and cookie, redirects to login; protected access denied afterwards; already expired is a failure | P `logout.php` (POST + CSRF; GET never logs out) → `logoutSession` (clear data, expire cookie, `session_destroy`); `requireLogin` sends `Cache-Control: no-store` | UM-10-T01…T06 (CSRF/role helpers); **E2E-17** (GET no-op, bad token 403, valid logout 303, cookie expired, protected page → login, **replay of the old session cookie fails and the session file is gone**, already-expired path), E2E-16 (20-minute timeout, simulated) | **Verified** | The timeout was simulated by ageing the server-side session file, not by waiting 20 minutes |

### Design acceptance tests (section "Traceability and acceptance tests")

| Use case | Acceptance test in the design | Executed evidence |
|---|---|---|
| UM-01 | Valid Student/Tutor registration creates Active user; duplicate rejected | UM-01-T01, T02, T05, T06; E2E-09 |
| UM-02 | Only own profile visible | UM-02-T03; E2E-06 |
| UM-03 | Admin search returns matching accounts | UM-03-T02…T07; E2E-08 |
| UM-04 | Name/email update succeeds; role modification denied | UM-04-T01, T02, T08; E2E-10 |
| UM-05 | New password works; old one fails | UM-05-T06, T07; E2E-11 |
| UM-06 | Unauthorized role change denied | UM-06-T03, T04; E2E-07 |
| UM-07 | Inactive user cannot log in | UM-07-T03; E2E-14 |
| UM-08 | Deletion cancelled or blocked by policy | UM-08-T03…T05; E2E-15 (Cancel is a link back; no write occurs) |
| UM-09 | Valid login creates session | UM-09-T01; E2E-05 |
| UM-10 | Protected access denied after logout | E2E-17 |

### Business rules and handout rules

| Rule (source) | Implementation | Evidence | Status |
|---|---|---|---|
| Student/Tutor self-register, Active immediately (Approved) | `registerUser` | UM-01-T01/T02/T11 | Verified |
| Public registration cannot grant Administrator (Approved) | role check first in `registerUser`; form offers two roles only | UM-01-T08/T09; E2E-09 (tampered POST → 403, no account) | Verified |
| Email is unique (Proposed) | `users.email UNIQUE`, app-level check, lower-casing | UM-01-T05/T06/T07, SCHEMA-T05 | Verified |
| Only administrators change others' role/status (Proposed) | `assertAdministrator` + `requireRole` | UM-06-T03, UM-07-T07; E2E-07 | Verified |
| Inactive users cannot log in (Proposed) | `authenticateUser`, `evaluateSession` | UM-07-T03/T04; E2E-14 | Verified |
| Prevent removal of the last active administrator (Proposed) | guarded SQL in `userPDO.php` (role, status, delete) | UM-06-T08…T10, UM-07-T11, UM-08-T12/T13; E2E-13/14/15 | Verified |
| Password hashes only; confirmation/current password never stored (Security / data dictionary) | `password_hash`; `users` has the 7 approved columns | UM-01-T03/T04, UM-05-T08 | Verified |
| Server-side validation, authorization checks, session protections (Security) | services + `includes/auth.php` | all of the above | Verified |
| Handout §13 access matrix (Student/Tutor/Administrator × pages) | `requestOwnerScope`, `requireRole`, `findRequestForUser`, owner-restricted `UPDATE`s | AUTH-T01…T26; E2E-07, E2E-12 | Verified |
| Handout §14 ownership in SQL; 404 for inaccessible record | `requestPDO.php` | AUTH-T09, T20…T22; E2E-12 | Verified |
| Handout §17 CSRF on state changes; §16 POST logout | `csrfField`, `verifyCsrfToken` on every POST page | UM-10-T01…T04; E2E-04/09/10/12/13/15/17 (each POST page checked without a token) | Verified |
| Handout §10 20-minute inactivity timeout | `SESSION_TIMEOUT_SECONDS` | UM-02-T04/T05; E2E-16 (simulated) | Verified |
| Reconcile USER with `students` / `tutoring_requests` (Design, "before implementation") | `students.user_id`; `setup-database.php` upgrade | SCHEMA-T01/T02; upgrade run on the real Lab 6 database (3 requests kept, FK check clean) | Verified (upgrade run manually, see `CHANGES.md`) |
| Lab 6 UC01 / UC02 behaviour preserved | `requestService.php`, request pages | UC01-T01…T22, UC02-T01…T07; E2E-12 | Verified |

## 4. Backward traceability (code → requirement)

| Feature / code path | Justification | Assessment |
|---|---|---|
| `students.user_id`, `setup-database.php` upgrade routine | Handout §2; design "reconcile USER with STUDENTS" | Justified |
| `password_needs_rehash` on login | Handout §6 | Justified |
| Dummy-hash verification for unknown emails | Supports Handout §7 (don't reveal which emails exist) | Informational: hardening beyond the text; low complexity |
| `Cache-Control: no-store`, `X-Frame-Options`, `X-Content-Type-Options` | Supports UM-10 acceptance ("protected access denied after logout", incl. Back button) and Handout §18 defence in depth | Informational: not explicitly required |
| `X-Forwarded-Proto` in the cookie `secure` decision | Handout §4 Codespaces note | Justified |
| Router allow-list | Lab 6 README already relied on the router to protect internals; extended because more sensitive code now exists | Informational: not a design requirement |
| Flash messages, `renderErrorPage`, `inputString` | Usability / robustness (arrays in place of strings) | Informational |
| Administrator may delete their own account when another active admin exists (session ended) | Follows from UM-08 + last-admin rule; no extra rule invented | Informational |
| `students.user_id` sync on UM-04 | Keeps JOIN-based request lists consistent with the account | Justified (consequence of UM-04 + reconciliation) |

No feature was found that contradicts an approved requirement.

## 5. Architecture and database findings

| # | Severity | Finding | Business impact / note |
|---|---|---|---|
| A1 | Informational | Layering preserved: no SQL in pages, no HTML in repositories (except `renderErrorPage` in `includes/helpers.php`, a presentation helper), transactions only in services (`submitRequest`, `updateOwnProfile`, `deleteUserAccount`). `includes/auth.php` calls a repository directly, exactly as the handout's `requireLogin` does. | Acceptable; keep new rules out of `auth.php`. |
| A2 | Informational | Schema drift is limited to the approved additions (`users`, `students.user_id`). `tutoring_requests` and the `topic` JSON convention are unchanged. | Satisfies "do not change the approved design without approval" via Handout §2. |
| A3 | Minor | `users.email` UNIQUE is case-sensitive in SQLite; uniqueness across case is enforced by the application (lower-casing). | A script inserting mixed-case emails directly could create look-alike accounts. |
| A4 | Minor | Lab 6 student rows are never auto-linked to new accounts (unverified email). A new account whose email matches an unlinked row gets a clear 409 on its first request. | Intended; resolution is an administrator task not yet in the UI. |
| A5 | Informational | Last-administrator protection is inside the `UPDATE`/`DELETE` statements, so two simultaneous requests cannot both pass a "count first" check. Not exercised with concurrent requests (see §7). | — |
| A6 | Informational | Every database write found uses parameters; foreign keys are enabled on every connection through `openDatabase`. | — |

## 6. Security limitations

| Severity | Limitation | Business impact |
|---|---|---|
| **Major** (accepted for classroom scope) | No rate limiting / lockout on login; no email verification; no password reset | Online password guessing is unthrottled; a forgotten password needs an administrator. |
| Major (deployment) | Cookie is only `Secure` when the request arrives over HTTPS (or `X-Forwarded-Proto: https`); plain HTTP exposes the session cookie | Use the HTTPS Codespaces URL; do not use plain HTTP outside localhost. |
| Minor | PHP's built-in server and file sessions; no CSP; no audit log of role/status/deletion changes | Fine for the lab; add for production. |
| Minor | Registration reveals whether an email is already registered (required by UM-01 "duplicate rejected") | Account enumeration is possible through the registration form; login itself does not leak. |
| Informational | Password rule is a default (8–72 bytes, letter + digit) | See open decision 2. |

## 7. Test evidence assessment

* **What was executed** (`docs/test-results.txt`): `php -l` on all 39 PHP files (0 errors); `php tests/run-all.php` (124 + 67 checks, 0 failed);
  `bash tests/e2e-http-tests.sh` (221 checks, 0 failed, no PHP warnings logged by the server); the unchanged Lab 4 validator file; and the negative control
  (`--negative-control` exits 1, so the harness can report failure).
* **Test quality.** Expected results come from the design's acceptance tests and the handout's matrix, not from reading the code. Tests call real
  service/repository functions, inspect database state after every denial (nothing changed), and run on disposable databases.
* **Mutation check (done during preparation, scripts not shipped):** 8 deliberate bugs were injected into the CLI-tested code (administrator
  self-registration, no last-admin guard, inverted ownership, inactive login allowed, deletion not blocked, role via profile, UPDATE ownership dropped, posted email
  trusted) and 5 into the HTTP-tested code (role gate removed, logout CSRF removed, no session regeneration, session not destroyed, ownership bypass). **All 13 were detected.** Two
  were *initially missed or weakly caught* and the tests were strengthened (UM-08-T03 now asserts the policy message, since SQLite's foreign key otherwise masks a missing service check;
  the harness now treats uncaught exceptions/warnings as failures).
* **Why both layers matter:** the HTTP run found a real defect the CLI tests could not — `includes/auth.php` used constants from `validation.php` without loading it, so any page
  that did not also load the user service crashed. It was fixed and the suite re-run.
* **Lab 6 baseline:** the delivered Lab 6 integration test had 3 failing checks (seed-data counts); see `CHANGES.md`.

### Coverage gaps

* Concurrency (two administrators acting at once) is protected in SQL but not tested with parallel requests.
* The 20-minute timeout was simulated; the `Secure` cookie flag was verified from the response header only.
* No browser-level tests (JavaScript, rendering of the Bootstrap navbar), no accessibility audit, no load tests.
* PHP 7.3 – 7.4 compatibility is by construction (no 8.0 syntax; `session_set_cookie_params` array form needs 7.3) but was only executed on PHP 8.3.
* Lab 6's original UC01/UC02 requirement IDs were not supplied, so those rows are traced by use case name, not requirement ID.

## 8. Prioritised corrective actions

1. **(Major) Approve or change the deletion/retention policy** (UM-08) — `previewAccountDeletion()`. Until then UM-08 stays *Partial*.
2. **(Major) Confirm password requirements** — `validatePassword()`; the tests (VAL-T01/T02, UM-01-T10) encode the current default.
3. **(Major, before any wider use)** Add login throttling and a password-reset process; serve only over HTTPS.
4. **(Minor)** Decide how administrators should attach an unlinked Lab 6 student record to an account (currently only `setup-database.php --demo`).
5. **(Minor)** Add a case-insensitive unique index on `users.email` (`COLLATE NOCASE`) if the schema may change again.
6. **(Informational)** Run the suites on the PHP version used in the course (`php -v`) and record the result next to `docs/test-results.txt`.
