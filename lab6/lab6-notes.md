**Part 1: Set up**
 php -r '$p=new PDO("sqlite:database/tutoring.db"); $p->exec(file_get_contents("database/setup-database.sql")); foreach($p->query("SELECT name FROM sqlite_master WHERE type=\"table\" AND name NOT LIKE \"sqlite_%\"") as $r) echo $r["name"],"\n";'
    -Had to use AI to generate this command because sqlite3 was refusing to download the way you showed us last week. I could confirm the tables both appeared with this: php -r '$p=new PDO("sqlite:database/tutoring.db"); foreach(["students","tutoring_requests"] as $t){echo "== $t ==\n"; foreach($p->query("SELECT * FROM $t", PDO::FETCH_ASSOC) as $r) echo implode(" | ",$r),"\n";}'
        >Results: == students ==
            1 | Maria Lopez | maria.lopez@example.com
            == tutoring_requests ==
            1 | 1 | IS 6465 | PHP arrays and forms | 2026-11-03 | 14:00 | Open | 2026-11-16 04:27:59

**Part 2: Run app**
-The URL you opened in the browser: https://super-duper-space-broccoli-rj6rw95gr6jc5rx7-8000.app.github.dev/php/request-form.php
-A short statement confirming that your request was saved and displayed: The request was saved and displayed as expected.
-A screenshot of the request detail page: In the screenshots folder of the repo titled: "part2.png"

**Part 3: Test app**
| Test | What I Did | Expected Result | Actual Result | Pass or Fail |
| ---- | ---------- | --------------- | ------------- | ------------ |
| Submit a valid request | Put this in "Student asdasd Course IS 6465 Request details asdasdasdasdasdasdasdasdasdasd Topics Practice problems Preferred date 2026-11-17 Preferred time Afternoon Status Open" | A new tutoring request is created with status Open. | A new tutoring request is created with status Open. | Pass |
| View all requests | Clicked "All requests" after creating new request. | The new request appears in the request list. | The new request appears in the request list. | Pass |
| Filter requests | Filtered requests by "All courses" and "Cancelled" | Filtering by course or status shows matching requests. | Filtering by course or status shows matching requests. | Pass |
| Edit an Open request | Selected all topics for the new request. | The revised values are displayed. | The revised values are displayed. | Pass |
| Cancel an Open request | Cancelled the new request from the request details page. | The request status changes to Cancelled. | The request status changes to Cancelled. | Pass |
| Try to edit or cancel a cancelled request | Clicked view on the cancelled request. | The application does not allow the change. | The application does not allow the change, it does not even show a spot to cancel. | Pass |

**Part 4: Trace request**
1. Which file displays the tutoring request form?
-request-form.php
2. Which file receives the submitted form data?
-process-request.php
3. Which file opens the PDO connection to SQLite?
-db.php in the config folder.
4. Which file coordinates validation and the database transaction?
-requestService.php in the services folder.
5. Which files contain the SQL statements?
-studentPDO.php and requestPDO.php in the repositories folder.
6. Which table stores student information?
-students
7. Which table stores tutoring requests?
-tutoring_requests
8. How does a tutoring request connect to the student who submitted it?
-Through the foreign key, student_id.
Write two to four sentences explaining why SQL statements belong in repository files rather than directly in the form page.
-This allows each layer to do one job, and avoid over complexity. This allows only the repository to need updating instead of each page if there is a change. It also helps with consistency and helps allow for checks without a browser.

**Part 5: Run tests**
PASS UC01 create and Open
PASS Lab 4 checkbox values preserved
PASS Duplicate email reuses student
PASS UC02 course and status filter
PASS Invalid reason rejected
PASS Invalid submission writes nothing
PASS Edit Open request
PASS Updated reason persisted
PASS Cancel Open request
PASS Cancelled row retained
PASS Second cancellation rejected
PASS Unrelated request unchanged
PASS FK enforced
PASS UC02 empty filter result
All integration checks passed

Due to the issues I had with sqlite, I had to use AI to modify the tests page to get it to work. Otherwise, there was 3 tests that failed. This one line fixed it: $pdo->exec('DELETE FROM tutoring_requests; DELETE FROM students;');

**Part 6: Use AI Skills Pack**
**Application Builder**
Prompt:Explain how a tutoring request moves from php/request-form.php through PHP, PDO, and SQLite in this project. Identify the responsibility of each file.
Response: A request travels through the project like this:
Collect the request: request-form.php renders the form. Submitting it sends a POST to process-request.php. The form fields include student details, course, reason, topics, preferred date, and preferred time.
Handle the submission: process-request.php accepts only POST submissions from this form, opens the database connection, and calls the request service. On success, it redirects to the new request’s details page.
Open SQLite through PDO: db.php creates a PDO connection to database/tutoring.db, configures error and fetch behavior, and enables SQLite foreign-key checks.
Validate and coordinate: requestService.php validates the submitted values, maps the form fields to database fields, and starts a transaction. The reason and selected topics are encoded together as JSON in the database’s single topic column.
Run database operations: studentPDO.php inserts the student if their email is not already present, then looks them up by email. requestPDO.php uses PDO prepared statements to insert the tutoring request and return its generated ID. The service commits both operations together, or rolls them back if something fails.
Store and display the result: setup-database.sql defines the students and tutoring_requests tables, including the student foreign key and default Open status. SQLite persists the submitted records in tutoring.db. request-details.php then loads and displays the saved request.
In short: the form gathers data, PHP routes and validates it, the service coordinates the work, PDO sends prepared SQL statements, and SQLite stores the student and request records.
Evaluation: Gave a good step by step explanation of how it works behind the scenes. I verified that process-request.php redirects to the details page on request.
**Traceability Auditor**
Prompt:Trace the requirement “a student can submit a tutoring request” through the PHP files, service, repositories, SQLite tables, and integration tests in this project.
Response:Request flow
Form: request-form.php collects the student’s name and email, course, reason, selected topics, preferred date, and preferred time. It posts those values to process-request.php.
HTTP handling: process-request.php requires a POST and the expected form marker, opens the database connection, and calls submitRequest(). It redirects to the new request’s details page on success; invalid submissions get a 422 response.
Validation and transaction: requestService.php validates the submitted values, packages the reason and topics as JSON for the topic field, then starts a transaction. It creates or finds the student, inserts the request, and commits; an error rolls back the transaction. validation.php checks allowed topics and times, reason length, and date-related input.
Repository operations: studentPDO.php inserts a student if their email is new and looks them up by email. requestPDO.php inserts the request with the student’s ID and returns its generated request ID. Both use PDO prepared statements.
SQLite storage: setup-database.sql defines students and tutoring_requests. Each request references a student through student_id; new requests default to Open. The runtime connection to tutoring.db is configured in db.php.
Confirmation: request-details.php retrieves the saved request and displays its student, course, details, date, time, and status.
What the integration tests verify
integration-tests.php loads the schema into an in-memory SQLite database and exercises the service/repository flow. It checks that a submitted request is created with Open status, topics are preserved, repeat submissions with the same email reuse one student, invalid input writes no request, and the student foreign key is enforced. It tests the database-backed flow rather than simulating a browser POST to the form handler.
Evaluation:Does not do a good explanation even though it is a bit wordy. I need to refine the prompt to get a better response. It said validation.php checks "date-related" input, but it does not.
| Requirement | Application Page | Service or Validation | Repository or SQL | Database Table | Test Evidence |
| ----------- | ---------------- | --------------------- | ----------------- | -------------- | ------------- |
| Student submits a tutoring request | Goes from the request-form.php -> process-request.php | requestService.php and validation.php | studentPDO.php; requestPDO.php; setup-database.sql | students and tutoring_requests | integration-tests.php: verifies request creation with Open status, topic preservation, student reuse by email, invalid-input rejection, and foreign-key enforcement. Also check the folder screenshots/part6-submitted_request.png |
**Test Harness Generator**
Prompt:Suggest one useful test case for the Peer Tutoring Hub application. The test must relate to request creation, filtering, editing, cancellation, validation, or database integrity.
Response:Test case: Reject a past preferred date without writing data. In integration-tests.php, submit a valid request with preferreddate set to yesterday and a new student email. Expect submitRequest() to throw InvalidArgumentException, and verify that neither the student nor the tutoring-request row was added. This checks date validation and ensures invalid submissions leave the database unchanged.
Evaluation:
1. Whether it is already covered by tests/integration-tests.php.
-It is not covered by the tests.
2. How you would test it manually or add it to the integration test file.
-I would test it by adding it to the integration test file since this validation is not already covered. I used AI to help me do this, but it essentially takes the sample test, converts the string to a time data type, then tries to submit the request, and outputs whether the failure actually happened. If it did, the test passes.
// Past date should be rejected
$pastDate = $sample;
$pastDate['preferreddate'] = date('Y-m-d', strtotime('-1 day'));
try {
    submitRequest($pdo, $pastDate);
    check(false, 'Past date rejected');
} catch (InvalidArgumentException $e) {
    check(true, 'Past date rejected');
}
$count = (int)$pdo->query('SELECT COUNT(*) FROM tutoring_requests')->fetchColumn();
check($count === 2, 'Past date adds no request');
3. Whether the AI generated test is appropriate for this application.
-This is definitely appropriate for this application.
Write three to five sentences explaining how the AI tool helped you understand, trace, or test the application. Explain why developers must verify AI generated outputs against actual code and requirements.
-It read the backend and understood it much faster than I could. Using this, it helped me to verify the path the submissions take and the validations take. It did have an error, which is why just copying and pasting output can be bad since it could have caused issues. It misunderstood the validation.

**Part 7: Inspect the Database**
Explain what the JOIN does in this query.
-It connects the student table by its primary key of student_id and the tutoring _requests table by its foreign key of student_id so that data from both tables can be pulled.