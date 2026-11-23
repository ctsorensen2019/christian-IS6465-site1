# Peer Tutoring Hub — Lab 7 (User Management, Authentication and Authorization)

Lab 6's tutoring-request application, extended with the ten User Management use cases
**UM-01 – UM-10** from the *User Management Design Document*, using the practices in the
*IS 6465 Authentication Reading Handout* (password hashing, server-side sessions, CSRF tokens,
role and ownership checks).

The Lab 6 three-tier structure is unchanged:

```
Browser -> php/*.php page -> services/*.php -> repositories/*.php -> SQLite (database/tutoring.db)
              (HTML only)     (rules, transactions)   (SQL only)
```

`includes/auth.php` is the one shared guard every protected page calls first.

## Run it (GitHub Codespaces or any PHP 7.3+ with PDO SQLite and mbstring; tested on PHP 8.3)

```bash
cd lab7
php -S 0.0.0.0:8000 php/router.php        # then open the forwarded port 8000
```

`database/tutoring.db` is **not** included: you create it (Part 2 of the lab), from the `lab7` folder:

```bash
cd database
sqlite3 tutoring.db < setup-database.sql                    # Lab 6 structure: students, tutoring_requests
sqlite3 tutoring.db < upgrade-to-user-management.sql        # adds users + students.user_id (keeps existing rows)
sqlite3 tutoring.db ".tables"                               # students  tutoring_requests  users
php create-admin.php "Test Administrator" admin@example.com "TestPassword123"
cd ..
php -S 0.0.0.0:8000                                         # then open port 8000
```

Run the upgrade once; a second run stops at "duplicate column name: user_id" and changes nothing.
`create-admin.php` takes name, email and password as arguments; if the password is left out it is prompted for
(hidden) or read from the `CREATE_ADMIN_PASSWORD` environment variable. `--db=path` selects another file.

Shortcut without the `sqlite3` program (also creates the demo accounts below):

```bash
php database/setup-database.php --demo        # applies both SQL files, then adds demo accounts
php database/setup-database.php --db=/tmp/x.db   # schema only, another file
```

### Demo accounts (fictional; for the classroom only)

| Email | Password | Role | Use it to try |
|---|---|---|---|
| `admin@example.edu` | `Admin123!` | Administrator | UM-03, 06, 07, 08; all requests |
| `tutor@example.edu` | `Tutor123!` | Tutor | profile only; request pages answer 403 |
| `maria.lopez@example.com` | `Student123!` | Student | owns the Lab 6 requests; deletion is **blocked** |
| `alex@example.edu` | `Student123!` | Student | second student: ownership checks |
| `jordan.kim@example.edu` | `Student123!` | Student | no requests: deletion **allowed** |
| `inactive@example.edu` | `Student123!` | Student (Inactive) | login is refused |

These exist only if you ran `setup-database.php --demo`. Change or delete these accounts before putting the app anywhere other than a class demo.
Only password *hashes* are stored; the script creates them with `password_hash()` at run time.

## Where each use case lives

| Use case | Page (`php/`) | Service function (`services/userService.php`) |
|---|---|---|
| UM-01 Create account | `register.php` | `registerUser` |
| UM-02 View own account | `profile.php` | `getOwnProfile` |
| UM-03 View / search accounts | `users.php`, `user-details.php` | `searchUserAccounts`, `getUserForAdministrator` |
| UM-04 Update own account | `edit-profile.php` | `updateOwnProfile` |
| UM-05 Change password | `change-password.php` | `changeOwnPassword` |
| UM-06 Update role | `user-role.php` | `changeUserRole` |
| UM-07 Activate / deactivate | `user-status.php` | `setUserStatus` |
| UM-08 Delete account | `user-delete.php` | `previewAccountDeletion`, `deleteUserAccount` |
| UM-09 Log in | `login.php` | `authenticateUser` (+ `establishSession` in `includes/auth.php`) |
| UM-10 Log out | `logout.php` | `logoutSession` in `includes/auth.php` |

The full requirement → wireframe → page → service → repository → table → test mapping, with
verification status, is in **`docs/TRACEABILITY.md`**. What changed from Lab 6 is in **`docs/CHANGES.md`**.

## Access rules (Handout section 13)

| | Student | Tutor | Administrator |
|---|---|---|---|
| Own profile, edit profile, change password | yes | yes | yes |
| Submit a tutoring request | yes | no (403) | no (403) |
| View requests | own only | no (403) | all |
| Edit / cancel a request | own **Open** requests | no (403) | any **Open** request |
| Manage users (list, role, status, delete) | no (403) | no (403) | yes |

A student who asks for someone else's request gets **404**, the same answer as for a request that
does not exist. Anything a logged-out visitor requests redirects to `login.php`.

## Security controls implemented (and which file does it)

| Control | Where |
|---|---|
| `password_hash(PASSWORD_DEFAULT)` / `password_verify`, rehash on login | `userService.php` |
| Generic "Invalid email or password." for unknown, inactive or wrong; dummy hash keeps timing similar | `userService.php::authenticateUser` |
| Cookie `httponly`, `samesite=Lax`, `secure` over HTTPS (also behind a proxy); strict session mode | `includes/auth.php::startSecureSession` |
| New session ID at login and after a password change | `establishSession`, `change-password.php` |
| Role and status reloaded from SQLite on **every** request; inactive user is signed out immediately | `evaluateSession` / `requireLogin` |
| 20-minute inactivity timeout | `SESSION_TIMEOUT_SECONDS` in `includes/auth.php` |
| CSRF token on every state-changing form (login and logout included), constant-time compare | `csrfField`, `verifyCsrfToken` |
| Logout clears data, expires the cookie, destroys the server session; `Cache-Control: no-store` on protected pages | `logoutSession`, `requireLogin` |
| Role checks on every protected page **and** again inside the service | `requireRole`, `assertAdministrator`, `requestOwnerScope` |
| Ownership enforced inside the SQL (`WHERE ... s.user_id = ?`) | `repositories/requestPDO.php` |
| Last active administrator cannot be demoted, deactivated or deleted (atomic, inside the SQL) | `repositories/userPDO.php` |
| Prepared statements everywhere; output encoded with `escapeHtml` | all repositories / pages |
| Router allow-list: `includes/`, `services/`, `database/`, `tests/`… cannot be requested by URL | `php/router.php` |

## Decisions the design left open (please confirm or change)

The design document lists these as **Open**. I implemented a conservative default and kept each in one place:

1. **Deletion policy / retention (UM-08).** An account with *any* tutoring request (including Cancelled ones) cannot be deleted;
   the admin is told why and nothing is removed. An account with none is deleted together with its empty student profile row.
   *Change:* `previewAccountDeletion()` in `services/userService.php`.
2. **Password requirements.** 8–72 bytes with at least one letter and one number (72 is bcrypt's limit).
   *Change:* `validatePassword()` in `includes/validation.php`.
3. **"Dashboard" after login (UM-09 step 3).** No dashboard screen is specified, so login lands on the profile page
   (as in the handout), which lists what the role can do.
4. **Lab 6 student rows are never auto-attached to a new account by email**, because a self-registered email is unverified
   and would let someone claim another person's requests. `setup-database.php --demo` links them (operator-run). If a new account's
   email collides with an unlinked Lab 6 row, the student gets a clear message and must ask an administrator.
5. **Email is stored lower-case and trimmed**; the database's UNIQUE index is case-sensitive, so the application normalises.

## Tests

```bash
php tests/run-all.php               # 207 checks: UM-01..10 (124), request workflow / access matrix / schema (67), upgrade SQL + create-admin (16)
bash tests/e2e-http-tests.sh        # 221 checks over real HTTP: cookies, sessions, CSRF, logout, direct-URL access
php tests/validation-tests.php      # the original Lab 4 validator checks (unchanged, prints HTML)
php tests/integration-tests.php --negative-control    # must FAIL (exit 1): proves the harness can detect failure
```

All tests use throw-away databases (`sqlite::memory:` or a temp file); `database/tutoring.db` is never opened by a test.
`docs/test-results.txt` holds the output of the run made while preparing this package.

## Known limitations (classroom application)

- **No brute-force protection** (rate limiting / lockout) on login, and no email verification or password-reset flow.
- Run over **HTTPS** in real use (Codespaces forwarding is HTTPS). Plain HTTP sends the session cookie without the `Secure` flag.
- The user list has no pagination.
- PHP's default file-based sessions are used; the 20-minute timeout is enforced by the application, not by PHP's garbage collector.
- Not hardened for public deployment (no CSP, no audit log, built-in PHP server). The router is a classroom stand-in for a proper
  document root.
- UM-10 and the timeout were verified over HTTP with the timeout simulated by ageing the server-side session file, not by waiting 20 minutes.
- The browser-visible look of the Bootstrap navbar was not checked (the build sandbox cannot reach the Bootstrap CDN); the custom pages and forms were.
