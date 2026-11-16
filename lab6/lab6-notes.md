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
-router.php
4. Which file coordinates validation and the database transaction?
-requests.php
5. Which files contain the SQL statements?
-setup-database.sql
6. Which table stores student information?
-students
7. Which table stores tutoring requests?
-tutoring_requests
8. How does a tutoring request connect to the student who submitted it?
-Through the foreign key, student_id.

**Part 5: Run tests**


**Part 6: Use AI Skills Pack**
**Application Builder**
Prompt:
Response:
Evaluation:
**Traceability Auditor**
Prompt:
Response:
Evaluation:
| Requirement | Application Page | Service or Validation | Repository or SQL | Database Table | Test Evidence |
| ----------- | ---------------- | --------------------- | ----------------- | -------------- | ------------- |
| Student submits a tutoring request |  |  |  |  |  |
**Test Harness Generator**
1. Whether it is already covered by tests/integration-tests.php.
-
2. How you would test it manually or add it to the integration test file.
-
3. Whether the AI generated test is appropriate for this application.
-

**Part 7: Inspect the Database**
Explain what the JOIN does in this query.
-