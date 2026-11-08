-- Part 1: Create tables

1. A screenshot or pasted output showing both table names and the table schema.
.schema:
CREATE TABLE students (
  student_id INTEGER PRIMARY KEY,
  student_name TEXT NOT NULL,
  student_email TEXT NOT NULL UNIQUE
);
CREATE TABLE tutoring_requests (
  request_id INTEGER PRIMARY KEY,
  student_id INTEGER NOT NULL,
  course TEXT NOT NULL,
  topic TEXT NOT NULL,
  preferred_date TEXT NOT NULL,
  preferred_time TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'Open',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id)
);
.tables:
students           tutoring_requests

@ctsorensen2019 ➜ /workspaces/christian-IS6465-site1/lab5 (main ✗) $ 

2. Which column is the primary key in each table?
students= student_id; tutoring_requests=request_id
3. Which column is the foreign key, and what relationship does it represent?
In tutoring_requests, the foreign key is student_id. It represents the column on which the two tables can be joined. It is a unique identifier that both tables share.

4. Why is student_email marked UNIQUE?
So that it cannot be reused for more than one user. In a real database this is because no two people can have the same email.

-- Part 2: Insert students and tutoring requests

1. The INSERT statements you used or a screenshot of them in lab5.sql.

INSERT OR ignore INTO students (student_name, student_email)
VALUES ('Jordan Lee', 'jordan.lee@example.edu');
INSERT OR ignore INTO students (student_name, student_email)
VALUES ('Avery Patel', 'avery.patel@example.edu');


INSERT OR ignore INTO tutoring_requests
  (student_id, course, topic, preferred_date, preferred_time)
VALUES
  (1, 'IS 6465', 'FORMS', '2026-11-10', '14:00');

INSERT OR ignore INTO tutoring_requests
  (student_id, course, topic, preferred_date, preferred_time)
VALUES
  (1, 'IS 6465', 'SQL SELECT', '2026-11-21', '14:00');

INSERT OR ignore INTO tutoring_requests
  (student_id, course, topic, preferred_date, preferred_time)
VALUES
  (2, 'IS 6465', 'SQLite CRUD', '2026-11-15', '14:00');

2. A screenshot or pasted SELECT * output that shows the rows in both tables.
1|Jordan Lee|jordan.lee@example.edu
2|Avery Patel|avery.patel@example.edu
1|1|IS 6465|FORMS|2026-11-10|14:00|Open|2026-11-08 08:54:41
2|1|IS 6465|SQL SELECT|2026-11-21|14:00|Open|2026-11-08 08:54:41
3|2|IS 6465|SQLite CRUD|2026-11-15|14:00|Open|2026-11-08 08:54:41

3. An explanation of why Jordan has one student row but two request rows.

-- Part 3: Read data

1. What does the JOIN condition connect?

2. What is the difference between the purpose of JOIN and WHERE?

3. Why should the application retrieve one request by request_id instead of by topic alone?

-- Part 4: Update 1 request

1. The two UPDATE statements you used.

2. A before-and-after screenshot or pasted SELECT output for the revised request.

3. A screenshot or pasted query result showing Avery's Cancelled status.

4. Why is a specific WHERE clause essential in an UPDATE statement?

5. Why does the Peer Tutoring Hub use a Cancelled status instead of immediately deleting a real request?

-- Part 5: Delete 1 request

1. The INSERT statement for the test request.

2. The DELETE statement you used.

3. A screenshot or pasted output that verifies the test record was removed.

4. Why is a WHERE clause essential in a DELETE statement?

-- Part 6: Reflect on CRUD

| CRUD action | SQL statement used in this lab | Peer Tutoring Hub example |
| ----------- | ------------------------------ | ------------------------- |
|   Create	  |                                |                           |
|   Read	  |                                |                           |		
|   Update	  |                                |                           |		
|   Delete	  |                                |                           |		


1. How does the database preserve the connection between a student and that student's requests?

2. Which database constraint prevents two student rows from using the same email address?

3. What could happen if an UPDATE or DELETE statement did not include a WHERE clause?

4. When the application later receives values from an HTML form, why should PHP send those values as parameters instead of placing them directly into SQL text?