# Lab 7 Notes: Authentication & Authorization

## Part 1: Generate the Lab 7 Application

1. The AI prompt you used.


2. A list of the uploaded source files.


3. A brief explanation of how Lab 7 extends the Lab 6 application.


4. One example of generated code or a generated file that you verified against the design document or reading handout.



## Part 2: Set Up the Database

1. Both database setup commands.


2. A statement confirming that all three tables appeared.


3. A screenshot or pasted output showing the tables.



## Part 3: Create an Administrator Account

1. The command structure you used. Do not include your actual password.


2. A statement confirming that the administrator account was created.


3. The email address you used for the test administrator account.


4. The role assigned to this account.



## Part 4: Run the Application

1. The browser URL you opened.


2. A short statement confirming that the application opened successfully.


3. A statement describing what happened when you first accessed the application without being logged in.



## Part 5: Test the Student Workflow

| Test | What I Did | Expected Result | Actual Result | Pass or Fail |
|---|---|---|---|---|
| Register student account | | Student account is created | | |
| Log in as student | | Student can access protected student pages | | |
| View or update profile | | Profile information is displayed or saved | | |
| Create tutoring request | | A new request is created with status Open | | |
| Edit Open request | | Updated request information is displayed | | |
| View my requests | | Student sees the student's own requests | | |
| Log out | | Session ends and login page appears | | |

Screenshot: one screenshot showing successful student registration, the student profile, a submitted tutoring request, or the student request list.



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
