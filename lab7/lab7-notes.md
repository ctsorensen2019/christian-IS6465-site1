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


2. What is authorization in this application?


3. How is student access different from administrator access?


4. Which functions or pages should be limited to administrators?


5. What happens after logout if a user tries to visit a protected page directly?


Screenshot: one screenshot showing an administrator page, such as the user list or all tutoring requests.



## Part 7: Trace Registration and Login

### Student registration workflow

1. Which file displays the Create Account form?


2. Which file receives the submitted registration form?


3. Which file or function creates and verifies the CSRF token?


4. Which service validates the registration information?


5. Where is the password hashed?


6. Which repository files insert user and student data?


7. Which database tables receive records when a student registers?


### User login workflow

1. Which file displays the login form?


2. Which file receives the submitted login credentials?


3. Which repository retrieves a user by email address?


4. Where is the submitted password verified?


5. What session actions occur after successful login?


6. Why should the application regenerate the session ID after login?


7. How does the application determine whether the logged in user is a student or administrator?


### Password hashing and verification

Two to four sentences explaining why password hashing and password verification should occur in the application layer rather than in the presentation page or database setup script.



## Part 8: Run the Automated Tests

1. Complete output of `php tests/validation-tests.php`.


2. Complete output of `php tests/user-management-tests.php`.


3. Complete output of `php tests/integration-tests.php`.


4. For each test group, state whether the tests passed or failed.


5. If any integration test fails:
   1. Identify the failed test name.
   2. Describe what the test was checking.
   3. Briefly explain what code, requirement, or database behavior may need further investigation.



## Part 9: Use the AI Skills Pack Process

### Application Builder

Prompt:

> Explain how a student registration request moves from register.php through authentication checks, PHP services, repositories, PDO, and SQLite in the Peer Tutoring Hub application. Identify the responsibility of each file or layer.

AI response:


One accurate statement from the AI response, verified by checking the actual code:


### Traceability Auditor

Prompt:

> Trace the requirement "a student can register, log in, create a tutoring request, and log out" through the presentation pages, authentication code, services, repositories, SQLite tables, and automated tests in this project.

AI response:


| Requirement | Presentation Page | Authentication or Authorization | Service or Validation | Repository or SQL | Database Table | Test Evidence |
|---|---|---|---|---|---|---|
| Student registers an account | | | | | | |
| Student logs in | | | | | | |
| Student creates a tutoring request | | | | | | |
| User logs out | | | | | | |

### Test Harness Generator

Prompt:

> Suggest one useful test case for authentication or authorization in the Peer Tutoring Hub application. The test must relate to registration, login, logout, session protection, role based access, password validation, CSRF protection, or account status.

AI response:


1. Whether it is already covered by the provided test files.


2. How you would test it manually or add it to an automated test.


3. Whether the AI generated test is appropriate for this application.


### Skills Pack Reflection

Three to five sentences explaining how the AI tool helped you understand, trace, or test the application, and why developers must verify AI generated explanations, code, and tests against the actual requirements and source code.



## Part 10: Inspect the Database

Query results (pasted output or screenshot):


1. What the LEFT JOIN does in this query.


2. How the users table connects to the students table.


3. Why the application stores account information separately from student profile information.


4. Why an administrator account may not have a matching row in the students table.
