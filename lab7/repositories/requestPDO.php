<?php
// Repository for tutoring_requests. SQL only; no HTML and no business rules.
//
// Lab 7 ownership rule (handout section 14): a student may touch only requests whose
// student row belongs to their own user ID. The condition is part of the SQL, so the
// database never returns or changes another user's row. $ownerUserId = null means "no
// ownership restriction" and is passed ONLY by the service layer for administrators.

function insertTutoringRequest($pdo, $studentId, $data)
{
    $stmt = $pdo->prepare('INSERT INTO tutoring_requests(student_id,course,topic,preferred_date,preferred_time) VALUES(?,?,?,?,?)');
    $stmt->execute(array($studentId, $data['course'], $data['topic'], $data['preferred_date'], $data['preferred_time']));
    return (int)$pdo->lastInsertId();
}

// Unrestricted lookup (administrators).
function findRequest($pdo, $id)
{
    $stmt = $pdo->prepare('SELECT r.*,s.student_name,s.student_email FROM tutoring_requests r JOIN students s ON r.student_id=s.student_id WHERE r.request_id=?');
    $stmt->execute(array($id));
    return $stmt->fetch();
}

// Ownership-restricted lookup (students). Returns false for someone else's request.
function findRequestForUser($pdo, $id, $userId)
{
    $stmt = $pdo->prepare(
        'SELECT r.*,s.student_name,s.student_email
         FROM tutoring_requests r JOIN students s ON r.student_id=s.student_id
         WHERE r.request_id=? AND s.user_id=?'
    );
    $stmt->execute(array($id, $userId));
    return $stmt->fetch();
}

// UC02. $ownerUserId limits the list to one student's requests (null = all requests).
function listRequests($pdo, $course = '', $status = '', $ownerUserId = null)
{
    $sql = 'SELECT r.*,s.student_name FROM tutoring_requests r JOIN students s ON r.student_id=s.student_id WHERE 1=1';
    $args = array();
    if ($ownerUserId !== null) {
        $sql .= ' AND s.user_id = ?';
        $args[] = $ownerUserId;
    }
    if ($course !== '') {
        $sql .= ' AND r.course = ?';
        $args[] = $course;
    }
    if ($status !== '') {
        $sql .= ' AND r.status = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY r.request_id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

// $ownerUserId has no default on purpose: callers must decide explicitly.
function updateOpenRequest($pdo, $id, $data, $ownerUserId)
{
    $sql = "UPDATE tutoring_requests SET course=?,topic=?,preferred_date=?,preferred_time=? WHERE request_id=? AND status='Open'";
    $args = array($data['course'], $data['topic'], $data['preferred_date'], $data['preferred_time'], $id);
    if ($ownerUserId !== null) {
        $sql .= ' AND student_id IN (SELECT student_id FROM students WHERE user_id = ?)';
        $args[] = $ownerUserId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return $stmt->rowCount() === 1;
}

// Cancellation is a status change; the row is retained.
function cancelOpenRequest($pdo, $id, $ownerUserId)
{
    $sql = "UPDATE tutoring_requests SET status='Cancelled' WHERE request_id=? AND status='Open'";
    $args = array($id);
    if ($ownerUserId !== null) {
        $sql .= ' AND student_id IN (SELECT student_id FROM students WHERE user_id = ?)';
        $args[] = $ownerUserId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return $stmt->rowCount() === 1;
}
