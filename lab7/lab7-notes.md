# Lab 7 Notes: Authentication & Authorization

## Part 1: Generate the Lab 7 Application

1. The AI prompt you used.
"Generate code for all user management use cases UM-01 through UM-10 in the User Management Design Document.

Use the Application Builder, Test Harness Generator, and Traceability Auditor documents as implementation guidance. Follow the authentication, authorization, session management, password hashing, CSRF protection, and role based access practices in the Authentication Reading Handout.

Use lab6.zip as the existing Peer Tutoring Hub application to extend. Preserve the Lab 6 three tier architecture and update the application as needed to support user registration, login, logout, profile management, administrator access, and protected tutoring request workflows.

Return a complete Lab 7 application package with all updated and new files."

2. A list of the uploaded source files.
-skills pack.zip
-Peer_Tutoring_Hub_User_Management_With_Diagrams.docx
-lab6.zip
-IS6465_Authentication_Reading_Handout_v1.2_[16Nov2026].pdf

3. A brief explanation of how Lab 7 extends the Lab 6 application.
Lab 7 adds the important characteristic of authentication in. Without this, anyone would be able to access the data. Authentication allows for segregation of duties and is primarily a security-based requirement for applications.

4. One example of generated code or a generated file that you verified against the design document or reading handout.
"These were the instructions, it looks like you are missing upgrade-to-user-management.sql and create-admin.php. These are the full instructions, in lab7-notes.md I actually want you to leave templates for what each part asks for and leave them empty with only the prompt in the instructions. For example,
Part 2: Set Up the Database

Both database setup commands.
A statement confirming that all three tables appeared.
A screenshot or pasted output showing the tables. Okay, I am going to work on all of the parts manually on my own. You did not do any of the work for me, correct?"
Prompting the Claude Sonnet model with this information allowed me to make sure that I was not going to be incorrect according the instructions. I found it was missing two files from the structure, so I asked it to add them.


## Part 2: Set Up the Database

1. Both database setup commands.
php -r '$p=new PDO("sqlite:tutoring.db"); $p->exec(file_get_contents("setup-database.sql")); echo "setup-database.sql applied\n";'
php -r '$p=new PDO("sqlite:tutoring.db"); $p->exec(file_get_contents("upgrade-to-user-management.sql")); echo "upgrade-to-user-management.sql applied\n";'

Had to have the AI give me the setup commands in PHP, sqlite would not show in the environment, like last week.

2. A statement confirming that all three tables appeared.
All 3 tables appeared. Used this command to test: "php -r '$p=new PDO("sqlite:tutoring.db"); foreach($p->query("SELECT name FROM sqlite_master WHERE type=\"table\" AND name NOT LIKE \"sqlite_%\" ORDER BY name") as $r) echo $r["name"], "\n";'"

3. A screenshot or pasted output showing the tables.
students
tutoring_requests
users


## Part 3: Create an Administrator Account

1. The command structure you used. Do not include your actual password.
Usage: php create-admin.php "Full Name" email@example.com "Password"

2. A statement confirming that the administrator account was created.
Administrator account created for testadmin@example.com (role: Administrator)

3. The email address you used for the test administrator account.
testadmin@example.com

4. The role assigned to this account.
Administrator


## Part 4: Run the Application

1. The browser URL you opened.
https://orange-garbanzo-4xvg64p7vw6h5jwr-8000.app.github.dev/php/login.php

2. A short statement confirming that the application opened successfully.
It worked, this is what was shown in the terminal: [Mon Nov 23 02:39:03 2026] 127.0.0.1:45050 [200]: GET /php/login.php.

3. A statement describing what happened when you first accessed the application without being logged in.
I was taken to the login page and prompted for my email and password.


## Part 5: Test the Student Workflow

| Test | What I Did | Expected Result | Actual Result | Pass or Fail |
|---|---|---|---|---|
| Register student account | Logged out of admin account, went to "New Here? Create an account" on the login page, filled in the details with test data, and clicked the "Student" radio button. | Student account is created | A student account was created. | Pass |
| Log in as student | Returned to the login page and inputted the test data into the login fields from the previous test. | Student can access protected student pages | Student can access protected student pages after logging in successfully | Pass |
| View or update profile | Clicked "My Profile" at the top and then clicked edit and changed my name to "Student Guy1" | Profile information is displayed or saved | Profile information is displayed or saved as "Student Guy1" | Pass |
| Create tutoring request | Using valid test data, navigated to the "Request a session" link in the navigation bar and filled it in. | A new request is created with status Open | Could see the new open request in the "My requests" link from the navigation bar. | Pass |
| Edit Open request | From "My requests", I clicked "View", then "Edit" on the request from the previous test. Then I changed the details for the request slightly. | Updated request information is displayed | Updated request information is displayed with an excalamation point instead of a period. | Pass |
| View my requests | Navigated to the navigation bar link "My Requests" | Student sees the student's own requests | Student sees the student's own requests | Pass |
| Log out | Clicked "Log out" in the navigation bar | Session ends and login page appears | Session ends and login page appears with this in the address bar https://orange-garbanzo-4xvg64p7vw6h5jwr-8000.app.github.dev/php/login.php?logged_out=1 | Pass |

Screenshot: one screenshot showing successful student registration, the student profile, a submitted tutoring request, or the student request list.
-This can be found in "images/student-request-list.png"



## Part 6: Test the Administrator Workflow

1. What is authentication in this application?
The authentication is the login page that uses the database to see if the user is in the database.

2. What is authorization in this application?
The role based access is the authorization, so the role column in the database provides that information to the application.

3. How is student access different from administrator access?
Student access does not allow for management of other users or management of the requests of other users.

4. Which functions or pages should be limited to administrators?
System-wide changes, user management, management of all requests, and role management should be limited to only administrators.

5. What happens after logout if a user tries to visit a protected page directly?
The user is redirected to the login.php page. 

Screenshot: one screenshot showing an administrator page, such as the user list or all tutoring requests.
-Can be found at images/admin-page-part-6.png.


## Part 7: Trace Registration and Login

### Student registration workflow

1. Which file displays the Create Account form?
php/register.php

2. Which file receives the submitted registration form?
php/register.php

3. Which file or function creates and verifies the CSRF token?
includes/auth.php

4. Which service validates the registration information?
services/userService.php

5. Where is the password hashed?
services/userService.php

6. Which repository files insert user and student data?
repositories/studentPDO.php
repositories/userPDO.php

7. Which database tables receive records when a student registers?
users and students

### User login workflow

1. Which file displays the login form?
php/login.php

2. Which file receives the submitted login credentials?
php/login.php

3. Which repository retrieves a user by email address?
repositories/userPDO.php

4. Where is the submitted password verified?
authenticateUser() in services/userService.php, using password_verify

5. What session actions occur after successful login?
establishSession() is the session in includes/auth.php. It calls startSecureSession() and does the following processes: generates a cookie, generates session ID that contains: user ID, display name, role, and last activity, and then finally generates a new CSRF token

6. Why should the application regenerate the session ID after login?
Because of session fixation, an attacker may to try to use a known session ID to log in.

7. How does the application determine whether the logged in user is a student or administrator?
By using auth.php to validate the role in the tutoring.db.

### Password hashing and verification

Two to four sentences explaining why password hashing and password verification should occur in the application layer rather than in the presentation page or database setup script.
The biggest reason is segregation of duties and also minimal responsibility. To keep each page, layer, or script from doing too much and causing errors, each of them should do the least amount of jobs. Doing just one job is preferable. It is also easier for the security rules to be tested and trusted across the different pages in the application if it does from the application layer.


## Part 8: Run the Automated Tests

1. Complete output of `php tests/validation-tests.php`.
<p>Valid time: PASS</p>
<p>Missing time: PASS</p>
<p>Altered value: PASS</p>
<p>Multiple valid topics: PASS</p>
<p>Missing topics: PASS</p>
<p>Altered topic: PASS</p>
<p>Minimum length: PASS</p>
<p>Maximum length: PASS</p>
<p>Too short: PASS</p>
<p>Too long: PASS</p>

2. Complete output of `php tests/user-management-tests.php`.

== UM-01 Create User Account (WF-01) ==
PASS: UM-01-T01 valid Student registration creates an Active Student
PASS: UM-01-T02 valid Tutor registration creates an Active Tutor
PASS: UM-01-T03 only a password hash is stored (plain password appears in no column)
PASS: UM-01-T04 users table has the approved columns and no confirmation/current password column
PASS: UM-01-T05 duplicate email rejected, no row added
PASS: UM-01-T06 duplicate email rejected regardless of case and spaces
PASS: UM-01-T07 email is stored trimmed and lower-case
PASS: UM-01-T08 Administrator role through public registration is denied, no row added
PASS: UM-01-T09.1 unknown / empty / array role rejected, no row added
PASS: UM-01-T09.2 unknown / empty / array role rejected, no row added
PASS: UM-01-T09.3 unknown / empty / array role rejected, no row added
PASS: UM-01-T10 missing name rejected with a correction message, no row added
PASS: UM-01-T10 invalid email rejected with a correction message, no row added
PASS: UM-01-T10 too-short password rejected with a correction message, no row added
PASS: UM-01-T10 password without digit rejected with a correction message, no row added
PASS: UM-01-T10 password without letter rejected with a correction message, no row added
PASS: UM-01-T10 mismatched confirmation rejected with a correction message, no row added
PASS: UM-01-T11 new account is Active immediately (can log in)
PASS: UM-01-T12 SQL metacharacters are stored as data (prepared statements)

== UM-02 View Own Account (WF-02) ==
PASS: UM-02-T01 profile shows own name, email, role and status
PASS: UM-02-T02 profile never includes the password hash
PASS: UM-02-T03 each user sees only their own profile
PASS: UM-02-T04 session idle for more than 20 minutes is expired
PASS: UM-02-T05 session idle for exactly 20 minutes is still valid
PASS: UM-02-T06 no session user means anonymous (redirect to login)
PASS: UM-02-T07 session with no activity time is treated as expired

== UM-03 View and Search User Accounts (WF-03) ==
PASS: UM-03-T01 no filters lists every account
PASS: UM-03-T02 text search matches name, case-insensitively
PASS: UM-03-T03 text search matches email
PASS: UM-03-T04 role filter
PASS: UM-03-T05 status filter
PASS: UM-03-T06 text, role and status filters combine (Alex and 100% Real)
PASS: UM-03-T07 no matches returns an empty list
PASS: UM-03-T08 % in the search box is matched literally, not as a wildcard
PASS: UM-03-T09 _ in the search box is matched literally
PASS: UM-03-T10 Student is denied account search
PASS: UM-03-T10 Tutor is denied account search
PASS: UM-03-T11 invalid role filter rejected
PASS: UM-03-T12 invalid status filter rejected
PASS: UM-03-T13 search results never include password hashes
PASS: UM-03-T14 account details show permitted fields and related request count
PASS: UM-03-T15 details for a missing account -> not found
PASS: UM-03-T16 Student cannot open another account's details

== UM-04 Update Own Account (WF-02) ==
PASS: UM-04-T01 valid name and email update succeeds
PASS: UM-04-T02 new values persisted (email normalized)
PASS: UM-04-T03 role and status unchanged by a profile edit
PASS: UM-04-T04 another user's account is untouched
PASS: UM-04-T05 duplicate email rejected, account unchanged
PASS: UM-04-T06 keeping your own email while changing the name is not a duplicate
PASS: UM-04-T07 bad email rejected, account unchanged
PASS: UM-04-T07 blank name rejected, account unchanged
PASS: UM-04-T08 submitting role is denied and nothing at all is changed
PASS: UM-04-T08 submitting status is denied and nothing at all is changed
PASS: UM-04-T09 a forged user_id field cannot redirect the update to another account
PASS: UM-04-T10 student profile row stays in sync so request lists show the new name

== UM-05 Change Password (WF-04) ==
PASS: UM-05-T01 incorrect current password rejected, hash unchanged
PASS: UM-05-T02 mismatched confirmation rejected, hash unchanged
PASS: UM-05-T03 password that breaks the rules rejected, hash unchanged
PASS: UM-05-T04 after the failed attempts the old password still works
PASS: UM-05-T05 valid change succeeds
PASS: UM-05-T06 the new password logs in
PASS: UM-05-T07 the old password no longer works
PASS: UM-05-T08 a new password hash is stored (never the plain password)
PASS: UM-05-T09 another user's hash is untouched

== UM-06 Update User Role (WF-05) ==
PASS: UM-06-T01 administrator changes Student to Tutor
PASS: UM-06-T02 administrator changes it back
PASS: UM-06-T03 Student actor denied (privilege escalation blocked), role unchanged
PASS: UM-06-T03 Tutor actor denied (privilege escalation blocked), role unchanged
PASS: UM-06-T04 a user cannot promote themselves
PASS: UM-06-T05 unknown role rejected
PASS: UM-06-T06 missing account -> not found
PASS: UM-06-T07 "change" to the current role is rejected
PASS: UM-06-T08 the last active administrator cannot be demoted
PASS: UM-06-T09 an INACTIVE second administrator does not count as a replacement
PASS: UM-06-T10 demotion is allowed once another administrator is active
PASS: UM-06-T11 a stale "Administrator" role in the session is overridden by the database role

== UM-07 Activate or Deactivate Account (WF-06) ==
PASS: UM-07-T01 precondition: active user can log in
PASS: UM-07-T02 administrator deactivates the account
PASS: UM-07-T03 inactive user cannot log in (correct password)
PASS: UM-07-T04 an already-open session of a deactivated user is ended on the next request
PASS: UM-07-T05 other accounts are unaffected
PASS: UM-07-T06 reactivation restores login
PASS: UM-07-T07 non-administrator denied, status unchanged
PASS: UM-07-T08 unknown status rejected
PASS: UM-07-T09 Active -> Active is an invalid transition
PASS: UM-07-T10 missing account -> not found
PASS: UM-07-T11 the last active administrator cannot be deactivated

== UM-08 Delete User Account (WF-07) ==
PASS: UM-08-T01 warning screen reports related requests and that deletion is blocked
PASS: UM-08-T02 warning screen reports no related requests
PASS: UM-08-T03 deletion blocked by the retention policy (message names the related tutoring requests, not just a database error)
PASS: UM-08-T04 a blocked deletion changes no rows (user, student and requests all retained)
PASS: UM-08-T05 retained Cancelled requests also block deletion
PASS: UM-08-T06 non-administrator denied, account remains
PASS: UM-08-T07 missing account -> not found
PASS: UM-08-T08 account with no related requests is deleted
PASS: UM-08-T09 the user row and its empty student profile are gone
PASS: UM-08-T10 no other user or request row was removed
PASS: UM-08-T11 an account that never had a student profile can be deleted
PASS: UM-08-T12 the last active administrator cannot be deleted
PASS: UM-08-T13 an administrator can be deleted while another active administrator exists
PASS: UM-08-T14 SQLite foreign keys independently refuse to orphan student/request rows

== UM-09 Log In (WF-08) and session helpers ==
PASS: UM-09-T01 valid credentials return the user (without the hash)
PASS: UM-09-T02 wrong password fails
PASS: UM-09-T03 unknown email fails
PASS: UM-09-T04 inactive account fails even with the right password
PASS: UM-09-T05 email is matched trimmed and case-insensitively
PASS: UM-09-T06 empty credentials fail
PASS: UM-09-T07 passwords are case-sensitive
PASS: UM-09-T08 the dummy hash used for unknown emails is a valid bcrypt hash (so the timing is equalised)
PASS: UM-09-T09 login with an old low-cost hash succeeds
PASS: UM-09-T10 the stored hash is upgraded after a successful login (password_needs_rehash)
PASS: UM-09-T11 a valid session resolves to the current user row
PASS: UM-09-T12 a session for a deleted account is rejected

== UM-10 Log Out / CSRF / role helpers (pure functions; full logout flow is in e2e-http-tests.sh) ==
PASS: UM-10-T01 matching CSRF token accepted
PASS: UM-10-T02 different CSRF token rejected
PASS: UM-10-T03 missing CSRF token rejected (including empty == empty)
PASS: UM-10-T04 non-string CSRF token rejected
PASS: UM-10-T05 roleAllowed grants only listed roles
PASS: UM-10-T06 roleAllowed is exact-match and fails closed

== Validators ==
PASS: VAL-T01 password minimum length is 8
PASS: VAL-T02 password maximum is 72 bytes (bcrypt limit)
PASS: VAL-T03 email format and 254-character limit
PASS: VAL-T04 full name limit is 120
PASS: VAL-T05 self-registration roles exclude Administrator

Results: 124 passed, 0 failed

3. Complete output of `php tests/integration-tests.php`.

== UC01 Request Tutoring (regression) ==
PASS: UC01-T01 create request, default status Open
PASS: UC01-T02 Lab 4 checkbox topics and reason textarea preserved in the topic JSON
PASS: UC01-T03 request linked to the student row of the signed-in account (JOIN returns name and email)
PASS: UC01-T04 a second request by the same user reuses the student row
PASS: UC01-T05 two requests stored
PASS: UC01-T06 invalid reason rejected
PASS: UC01-T07 invalid course rejected
PASS: UC01-T07 missing course rejected
PASS: UC01-T07 invalid preferred time rejected
PASS: UC01-T07 no topics selected rejected
PASS: UC01-T07 altered topic value rejected
PASS: UC01-T07 past date rejected
PASS: UC01-T07 malformed date rejected
PASS: UC01-T08 invalid submissions write nothing
PASS: UC01-T09 quotes and HTML special characters round-trip exactly (output encoding happens on display)
PASS: UC01-T10 escapeHtml encodes quotes, angle brackets and ampersands
PASS: UC01-T11 edit an Open request
PASS: UC01-T12 updated reason persisted
PASS: UC01-T13 invalid edit rejected, stored request unchanged
PASS: UC01-T14 cancel an Open request
PASS: UC01-T15 cancelled row is retained with status Cancelled
PASS: UC01-T16 a second cancellation is rejected
PASS: UC01-T17 a Cancelled request cannot be edited
PASS: UC01-T18 unrelated requests unchanged
PASS: UC01-T19 unknown request ID: nothing found, nothing changed
PASS: UC01-T20 foreign key enforced (unknown student_id rejected)
PASS: UC01-T21 forced failure rolls back the whole transaction (no student row, no request, no open transaction)
PASS: UC01-T22 after the failure the same user can submit normally

== UC02 View and Filter Requests (regression) ==
PASS: UC02-T01 no filters returns all saved requests (administrator)
PASS: UC02-T02 course filter
PASS: UC02-T03 status filter
PASS: UC02-T04 combined course + status filter
PASS: UC02-T05 no match gives an empty list
PASS: UC02-T06 student information comes from the joined students table
PASS: UC02-T07 filter values are parameters (injection string matches nothing)

== Access matrix: submit (Student only) ==
PASS: AUTH-T01 Tutor cannot submit a request
PASS: AUTH-T02 Administrator cannot submit a request
PASS: AUTH-T03 no actor -> denied
PASS: AUTH-T04 denied submissions write nothing

== Access matrix: view and list ==
PASS: AUTH-T05 a student lists only their own requests
PASS: AUTH-T06 another student sees only theirs
PASS: AUTH-T07 filters apply inside the ownership restriction
PASS: AUTH-T08 a student can open their own request
PASS: AUTH-T09 a student asking for someone else's request gets "not found" (no information leak)
PASS: AUTH-T10 an administrator can open any request
PASS: AUTH-T11 Tutor cannot list requests
PASS: AUTH-T11 Tutor cannot view requests

== Access matrix: edit and cancel ==
PASS: AUTH-T12 a student cannot edit another student's Open request
PASS: AUTH-T13 ...and the stored request is unchanged
PASS: AUTH-T14 a student cannot cancel another student's Open request
PASS: AUTH-T15 an administrator can edit any Open request
PASS: AUTH-T16 an administrator can cancel any Open request (record retained)
PASS: AUTH-T17 an administrator still cannot edit a Cancelled request
PASS: AUTH-T18 Tutor cannot edit
PASS: AUTH-T19 Tutor cannot cancel, request unchanged

== Ownership enforced in SQL (repository level) ==
PASS: AUTH-T20 findRequestForUser returns nothing for a non-owner
PASS: AUTH-T21 findRequestForUser returns the owner's row
PASS: AUTH-T22 UPDATE statements themselves refuse a non-owner, even if the service layer were bypassed

== Identity comes from the account, not the form ==
PASS: AUTH-T23 posted student_name / student_email are ignored; the request belongs to the signed-in account
PASS: AUTH-T24 the spoofed submission did not appear in the victim's list

== Legacy (unlinked) student rows ==
PASS: AUTH-T25 a new account is NOT silently attached to an unlinked Lab 6 student row with the same email (unverified email)
PASS: AUTH-T26 the legacy student row is left untouched

== Requirement checks on schema ==
PASS: SCHEMA-T01 students.user_id references users.user_id
PASS: SCHEMA-T02 one user cannot own two student rows (students.user_id is UNIQUE)
PASS: SCHEMA-T03 users.role CHECK constraint rejects unknown roles
PASS: SCHEMA-T04 users.status CHECK constraint rejects unknown statuses
PASS: SCHEMA-T05 users.email is UNIQUE

Results: 67 passed, 0 failed

4. For each test group, state whether the tests passed or failed.

All of the tests passed for all of the test groups.


## Part 9: Use the AI Skills Pack Process

### Application Builder

Prompt:

> Explain how a student registration request moves from register.php through authentication checks, PHP services, repositories, PDO, and SQLite in the Peer Tutoring Hub application. Identify the responsibility of each file or layer.

AI response:

Student registration: register.php to SQLite

php/register.php (presentation layer). On GET it shows the form, which includes a hidden CSRF token from csrfField(). On POST it runs verifyCsrfToken($_POST), then calls registerUser(openDatabase(), $_POST). It catches exceptions and turns them into 403, 422 or 500 responses. On success it redirects to login.php?registered=1. It contains no SQL and no hashing.
includes/auth.php (authentication checks).
startSecureSession() starts the session with secure cookie settings.
csrfToken() creates a random token and keeps it in the session.
verifyCsrfToken() compares the submitted token against it with hash_equals. A bad token gets a 403 and nothing is written.
services/userService.php::registerUser (application layer / business rules).
Rejects the Administrator role.
Validates name, email, password, confirmation and role using includes/validation.php.
Checks for a duplicate email.
Hashes the password with password_hash(PASSWORD_DEFAULT).
Starts a transaction and calls the repositories.
repositories/userPDO.php::insertUser and studentPDO.php::insertStudentForUser (data-access layer). These contain only SQL, as prepared statements with ? placeholders. They insert into users and, for a Student, students (with user_id linking back to users).
config/db.php::openDatabase (PDO). Creates the PDO connection, turns on exception errors and enables PRAGMA foreign_keys.
SQLite (database/tutoring.db). Stores the rows and enforces the constraints: unique email, role and status CHECKs, and the foreign key from students.user_id to users.user_id.

One accurate statement from the AI response, verified by checking the actual code:

I went and verified the application layer/business rules in number 3. I tried creating a tutor account with the same email as the test student user I created earlier. It was denied and the screenshot can be found in images/part-9-verification.png.

### Traceability Auditor

Prompt:

> Trace the requirement "a student can register, log in, create a tutoring request, and log out" through the presentation pages, authentication code, services, repositories, SQLite tables, and automated tests in this project.

AI response:

Here is the traceability table, built from the actual code and tests in the project.

Requirement	Presentation Page	Authentication or Authorization	Service or Validation	Repository or SQL	Database Table	Test Evidence
Student registers an account	php/register.php (form and POST handler)	startSecureSession(); csrfField() in the form and verifyCsrfToken() on POST (includes/auth.php)	registerUser() in services/userService.php: blocks the Administrator role, runs validateFullName, validateEmail, validatePassword, validatePasswordConfirmation and validateRole, checks for a duplicate email, and calls password_hash()	insertUser() (repositories/userPDO.php) and insertStudentForUser() (repositories/studentPDO.php); INSERT with prepared statements	users, students	UM-01-T01 (valid Student registration), UM-01-T13 (linked students row), UM-01-T05/T06 (duplicate email), E2E-09 (registration over HTTP, 303 and "Account created.")
Student logs in	php/login.php	verifyCsrfToken(); establishSession() (regenerates session ID, stores user ID, role and last activity); requireLogin() on later pages	authenticateUser() in services/userService.php: password_verify(), Active status check, one generic failure message	findUserByEmail() (repositories/userPDO.php); SELECT ... WHERE email = ?	users	UM-09-T01 (valid login), UM-09-T02/T03/T04 (wrong password, unknown email, inactive account), E2E-04 (CSRF on login, generic failure message), E2E-05 (session ID changes at login)
Student creates a tutoring request	php/request-form.php (form) and php/process-request.php (POST handler)	requireLogin(), requireRole($user, array('Student')) and verifyCsrfToken()	submitRequest() in services/requestService.php: Student-only, name and email taken from the logged-in account, validateSubmission()	findStudentByUserId() / insertStudentForUser() (studentPDO.php) and insertTutoringRequest() (repositories/requestPDO.php)	tutoring_requests (linked through students)	UC01-T01 (request created, status Open), AUTH-T01 to T04 (non-students denied, nothing written), AUTH-T23 (posted name and email ignored), E2E-12 (request workflow over HTTP)
User logs out	php/logout.php (POST form button in the navigation)	verifyCsrfToken(); logoutSession() in includes/auth.php clears session data, expires the cookie and calls session_destroy()	None (no business rule)	None (no database access)	None	E2E-17 (GET doesn't log out, bad CSRF token gives 403, valid logout gives 303 to login, protected page then redirects to login, replaying the old cookie fails), UM-10-T01 to T04 (CSRF token checks)

Notes for your evaluation

Logout test gap: the CLI tests have no direct test of logoutSession(). The logout evidence comes mainly from the HTTP end-to-end tests (E2E-17), which php tests/run-all.php does not run. They run with bash tests/e2e-http-tests.sh.
Naming: UM-10 test IDs in user-management-tests.php cover the CSRF and role helpers that logout relies on, so don't describe them as logout tests.
Check before submitting: before you submit, open one or two of these files and confirm the function names yourself. For example, grep -n "function logoutSession" includes/auth.php.

| Requirement | Presentation Page | Authentication or Authorization | Service or Validation | Repository or SQL | Database Table | Test Evidence |
|---|---|---|---|---|---|---|
| Student registers an account | php/register.php | startSecureSession(); csrfField() in the form and verifyCsrfToken() on POST (includes/auth.php) | registerUser() in services/userService.php: blocks the Administrator role, runs validateFullName, validateEmail, validatePassword, validatePasswordConfirmation and validateRole, checks for a duplicate email, and calls password_hash() | Repository is insertUser() and insertStudentForUser() | users, students | user-management-tests.php: UM-01-T01 (valid registration), UM-01-T13 (linked students row), UM-01-T05/T06 (duplicate email) |
| Student logs in | php/login.php | verifyCsrfToken(); establishSession() (regenerates session ID, stores user ID, role and last activity); requireLogin() on later pages | authenticateUser() in services/userService.php: password_verify(), Active status check, one generic failure message | findUserByEmail() | users | user-management-tests.php: UM-09-T01 (valid login), UM-09-T02 (wrong password), UM-09-T03 (unknown email), UM-09-T04 (inactive account) |
| Student creates a tutoring request | php/request-form.php and php/process-request.php | requireLogin(), requireRole($user, array('Student')) and verifyCsrfToken() | submitRequest() in services/requestService.php: Student-only, name and email taken from the logged-in account, validateSubmission() | repositories/requestPDO.php and repositories/studentPDO.php | tutoring_requests | validation-tests.php: the three validators above (Valid time, Multiple valid topics, Minimum length, and so on). integration-tests.php: UC01-T01 (request created, status Open), AUTH-T01 to T04 (non-students denied) |
| User logs out | php/logout.php | verifyCsrfToken(); logoutSession() in includes/auth.php clears session data, expires the cookie and calls session_destroy() | None | None | None | images/logout-part-9.png |

### Test Harness Generator

Prompt:

> Suggest one useful test case for authentication or authorization in the Peer Tutoring Hub application. The test must relate to registration, login, logout, session protection, role based access, password validation, CSRF protection, or account status.

AI response:

Suggested test: a logged-out session cookie can't be reused

Scenario: A student logs in and the browser receives a session cookie. The student logs out. Someone who copied that cookie earlier tries to open a protected page, for example php/profile.php, with it.
Expected result: The server redirects (302) to login.php and doesn't show the profile. This proves logout destroys the session on the server instead of only deleting the browser's cookie.
Category: logout and session protection.

1. Whether it is already covered by the provided test files.

Partly by the UM-10, but not entirely. It would need to be tested manually.

2. How you would test it manually or add it to an automated test.

Manually, in the browser:

-Log in as your test student, then open DevTools → Application → Cookies and copy the PHPSESSID value.
-Click Logout.
-Replay the old cookie from the terminal (substitute your own port and value):
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" -b "PHPSESSID=PASTE_VALUE" http://localhost:8000/php/profile.php

3. Whether the AI generated test is appropriate for this application.
Yes, because cookie reuse is a real issue. A man in the middle attack would copy the cookie and try what is called "session hijacking". This a real and typical threat an application should protect against.

### Skills Pack Reflection

Three to five sentences explaining how the AI tool helped you understand, trace, or test the application, and why developers must verify AI generated explanations, code, and tests against the actual requirements and source code.
The AI tool was really good at telling me where the information it generated came from. The thing I had to do as a developer is verify the AI generated information to make sure everything was created correctly. AI will take the path of least resistance and sometimes is trained on bad information. Therefore, we need to check it like we would an intern's work, so that we meet the requirements we need to.


## Part 10: Inspect the Database


1. What the LEFT JOIN does in this query.
LEFT JOIN connects the students and users tables on the user IDs found on the student table. It will show all users, and any user that is not a student will have NULL columns for the students table columns.

2. How the users table connects to the students table.
It matches the user IDs found in both tables to provide the full information for each user via user.user_id and student via students.user_id.

3. Why the application stores account information separately from student profile information.
It follows the principle of minimal responsibility.

4. Why an administrator account may not have a matching row in the students table.
An administrator is not necessarily a student, and there is no students row for administrators.