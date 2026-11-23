-- Upgrade a Lab 6 database (students + tutoring_requests only) to Lab 7 user management.
-- Keeps every existing student and tutoring request.
--
--   sqlite3 database/tutoring.db < database/upgrade-to-user-management.sql
--
-- Run it ONCE on a Lab 6 database. If you run it again, the ALTER TABLE line reports
-- "duplicate column name: user_id" and changes nothing; the other statements are
-- harmless to repeat. Then create the first administrator:
--
--   php database/create-admin.php
--
-- Existing Lab 6 student rows start with user_id = NULL (not linked to any account).
-- The application never links them automatically by email, because a self-registered
-- email is unverified. To link one deliberately, an operator can run, for example:
--   UPDATE students SET user_id = (SELECT user_id FROM users WHERE email = 'maria@example.com')
--   WHERE student_email = 'maria@example.com';
--
-- The resulting schema is equivalent to database/setup-database.sql (the unique rule on
-- students.user_id is a unique index here because SQLite cannot ADD a UNIQUE column).

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    user_id       INTEGER PRIMARY KEY,
    full_name     TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL CHECK(role IN ('Student', 'Tutor', 'Administrator')),
    status        TEXT NOT NULL DEFAULT 'Active' CHECK(status IN ('Active', 'Inactive')),
    created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE students ADD COLUMN user_id INTEGER REFERENCES users(user_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_students_user_id ON students(user_id);
