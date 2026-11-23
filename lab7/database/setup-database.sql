-- Peer Tutoring Hub: base (Lab 6) database structure.
-- Run this first, then database/upgrade-to-user-management.sql adds the Lab 7 user accounts.
--
--   cd database
--   sqlite3 tutoring.db < setup-database.sql
--   sqlite3 tutoring.db < upgrade-to-user-management.sql
--
-- Safe to run more than once (CREATE TABLE IF NOT EXISTS). Contains NO sample rows, so the
-- automated tests start from an empty, predictable database.

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS students (
    student_id    INTEGER PRIMARY KEY,
    student_name  TEXT NOT NULL,
    student_email TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS tutoring_requests (
    request_id     INTEGER PRIMARY KEY,
    student_id     INTEGER NOT NULL,
    course         TEXT NOT NULL,
    topic          TEXT NOT NULL,
    preferred_date TEXT NOT NULL,
    preferred_time TEXT NOT NULL,
    status         TEXT NOT NULL DEFAULT 'Open' CHECK(status IN ('Open', 'Cancelled', 'Assigned', 'Completed')),
    created_at     TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(student_id) REFERENCES students(student_id)
);
