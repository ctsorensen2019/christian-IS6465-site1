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
This is because you cannot have repeats of the same student, there is only one Jordan Lee, but he has submitted two separate tutoring requests. That is why we have a foreign key to join the tables.

-- Part 3: Read data
SELECT
  r.request_id,
  s.student_name,
  s.student_email,
  r.course, r.topic,
  r.preferred_date, r.preferred_time, r.status
FROM tutoring_requests AS r
JOIN students AS s ON r.student_id = s.student_id
ORDER BY r.preferred_date;

1|Jordan Lee|jordan.lee@example.edu|IS 6465|FORMS|2026-11-10|14:00|Open
3|Avery Patel|avery.patel@example.edu|IS 6465|SQLite CRUD|2026-11-15|14:00|Open
2|Jordan Lee|jordan.lee@example.edu|IS 6465|SQL SELECT|2026-11-21|14:00|Open

1. What does the JOIN condition connect?
It connects the students and tutoring_requests tables on the key of student_id.

2. What is the difference between the purpose of JOIN and WHERE?
JOIN is something that connects all of the data between two tables on the foreign/primary key(s). A WHERE statement is like a filter in Excel, it narrows down the results.

3. Why should the application retrieve one request by request_id instead of by topic alone?
This is because request_id is a unique identifier, and pulling by topic alone may bring more than the results you desire. This can skew the data you bring back and the results you analyze.

-- Part 4: Update 1 request

1. The two UPDATE statements you used.
UPDATE tutoring_requests
SET topic = 'PHP arrays and forms - updated',
    preferred_date = '2026-10-12',
    preferred_time = '15:00'
WHERE request_id = 1;

UPDATE tutoring_requests
SET status = 'Cancelled'
WHERE request_id = 3;

2. A before-and-after screenshot or pasted SELECT output for the revised request.
Before=
1|1|IS 6465|FORMS|2026-11-10|14:00|Open|2026-11-08 09:11:45
2|1|IS 6465|SQL SELECT|2026-11-21|14:00|Open|2026-11-08 09:11:45
3|2|IS 6465|SQLite CRUD|2026-11-15|14:00|Open|2026-11-08 09:11:45

After= 
1|1|IS 6465|PHP arrays and forms - updated|2026-10-12|15:00|Open|2026-11-08 09:11:45
2|1|IS 6465|SQL SELECT|2026-11-21|14:00|Open|2026-11-08 09:11:45
3|2|IS 6465|SQLite CRUD|2026-11-15|14:00|Open|2026-11-08 09:11:45

3. A screenshot or pasted query result showing Avery's Cancelled status.
1|1|IS 6465|PHP arrays and forms - updated|2026-10-12|15:00|Open|2026-11-08 09:11:45
2|1|IS 6465|SQL SELECT|2026-11-21|14:00|Open|2026-11-08 09:11:45
3|2|IS 6465|SQLite CRUD|2026-11-15|14:00|Cancelled|2026-11-08 09:11:45

4. Why is a specific WHERE clause essential in an UPDATE statement?
This is because you could update the wrong column, and will update all of them if you do not specify a WHERE.

5. Why does the Peer Tutoring Hub use a Cancelled status instead of immediately deleting a real request?
So that you have a record of it. This would be helpful for reference if Avery wants to open a new request based on the cancelled one.

-- Part 5: Delete 1 request

1. The INSERT statement for the test request.
INSERT OR ignore INTO tutoring_requests
  (student_id, course, topic, preferred_date, preferred_time)
VALUES
  (2, 'IS 6465', 'Test Record', '2026-11-15', '14:00');

2. The DELETE statement you used.
DELETE from tutoring_requests
WHERE request_id=4;

3. A screenshot or pasted output that verifies the test record was removed.
1|1|IS 6465|PHP arrays and forms - updated|2026-10-12|15:00|Open|2026-11-08 09:19:39
2|1|IS 6465|SQL SELECT|2026-11-21|14:00|Open|2026-11-08 09:19:39
3|2|IS 6465|SQLite CRUD|2026-11-15|14:00|Cancelled|2026-11-08 09:19:39
4|2|IS 6465|Test Record|2026-11-15|14:00|Open|2026-11-08 09:19:39
1|1|IS 6465|PHP arrays and forms - updated|2026-10-12|15:00|Open|2026-11-08 09:19:39
2|1|IS 6465|SQL SELECT|2026-11-21|14:00|Open|2026-11-08 09:19:39
3|2|IS 6465|SQLite CRUD|2026-11-15|14:00|Cancelled|2026-11-08 09:19:39

4. Why is a WHERE clause essential in a DELETE statement?
You should use a WHERE caluse to make sure you delete the correct record.

-- Part 6: Reflect on CRUD

| CRUD action | SQL statement used in this lab | Peer Tutoring Hub example |
| ----------- | ------------------------------ | ------------------------- |
|   **Create**	  |         CREATE TABLE students (student_id INTEGER PRIMARY KEY,student_name TEXT NOT NULL,student_email TEXT NOT NULL UNIQUE);                      |             Creating the request table              |
|   **Read**	  |         SELECT r.request_id, s.student_name, s.student_email, r.course, r.topic, r.preferred_date, r.preferred_time, r.status FROM tutoring_requests AS r JOIN students AS s ON r.student_id = s.student_id ORDER BY r.preferred_date;                       |     Collecting all the requests by student who requested them.                      |		
| **Update**	  |           UPDATE tutoring_requests SET topic = 'PHP arrays and forms - updated', preferred_date = '2026-10-12', preferred_time = '15:00' WHERE request_id = 1;                     |         Updating the topic for a tutoring request when a student sends more information.                  |		
| **Delete**	  |         DELETE from tutoring_requests WHERE request_id=4;                       |            A student submitted a request by error.               |		


1. How does the database preserve the connection between a student and that student's requests?
It preserves the data across sessions, so that it is retained even if the connection is closed.

2. Which database constraint prevents two student rows from using the same email address?
The "UNIQUE" mark on student_email at creation of the table.

3. What could happen if an UPDATE or DELETE statement did not include a WHERE clause?
Whole tables could be deleted or updated without a WHERE clause.

4. When the application later receives values from an HTML form, why should PHP send those values as parameters instead of placing them directly into SQL text?
It is a safer way to communicate with the database, and will help prevent SQL injection.