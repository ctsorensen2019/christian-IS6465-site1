<?php
// Repository for the students table. In Lab 7 a student profile row belongs to exactly
// one user account through students.user_id.

function findStudentByEmail($pdo, $email)
{
    $stmt = $pdo->prepare('SELECT student_id, student_name, student_email, user_id FROM students WHERE student_email = ?');
    $stmt->execute(array($email));
    return $stmt->fetch();
}

function findStudentByUserId($pdo, $userId)
{
    $stmt = $pdo->prepare('SELECT student_id, student_name, student_email, user_id FROM students WHERE user_id = ?');
    $stmt->execute(array($userId));
    return $stmt->fetch();
}

// Replaces Lab 6 insertStudentIfAbsent(): a student row is now created for a specific
// account, never looked up by a name/email typed into a form.
function insertStudentForUser($pdo, $userId, $name, $email)
{
    $stmt = $pdo->prepare('INSERT INTO students (student_name, student_email, user_id) VALUES (?, ?, ?)');
    $stmt->execute(array($name, $email, $userId));
    return (int)$pdo->lastInsertId();
}

// Keep the student row (shown in request lists through a JOIN) in step with UM-04 edits.
function syncStudentProfile($pdo, $userId, $name, $email)
{
    $stmt = $pdo->prepare('UPDATE students SET student_name = ?, student_email = ? WHERE user_id = ?');
    $stmt->execute(array($name, $email, $userId));
}

// UM-08: remove the account's student profile row ONLY when it has no tutoring requests.
// A row that still has requests is left alone, which keeps the delete blocked by the
// foreign key rather than silently removing tutoring records.
function deleteEmptyStudentForUser($pdo, $userId)
{
    $stmt = $pdo->prepare(
        'DELETE FROM students WHERE user_id = ?
           AND NOT EXISTS (SELECT 1 FROM tutoring_requests r WHERE r.student_id = students.student_id)'
    );
    $stmt->execute(array($userId));
}
