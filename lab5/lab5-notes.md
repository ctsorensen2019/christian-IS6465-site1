-- Part 1: Create tables

1. A screenshot or pasted output showing both table names and the table schema.

2. Which column is the primary key in each table?

3. Which column is the foreign key, and what relationship does it represent?

4. Why is student_email marked UNIQUE?

-- Part 2: Insert students and tutoring requests

1. The INSERT statements you used or a screenshot of them in lab5.sql.

2. A screenshot or pasted SELECT * output that shows the rows in both tables.

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