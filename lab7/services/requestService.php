<?php
// Service layer for tutoring requests (UC01 / UC02), extended in Lab 7 with role and
// ownership rules from the handout's access matrix (section 13):
//
//   Student        submit; view / list / edit / cancel OWN requests (Open ones for edit/cancel)
//   Administrator  view / list ALL requests; edit / cancel ANY Open request; cannot submit
//   Tutor          no request workflow in this version
//
// The $actor argument is the row returned by requireLogin() (fresh from SQLite).

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/errors.php';
require_once dirname(__DIR__) . '/includes/validation.php';
require_once dirname(__DIR__) . '/repositories/studentPDO.php';
require_once dirname(__DIR__) . '/repositories/requestPDO.php';

// Week 4 form fields map to the Week 6 schema without adding database columns.
// topic contains JSON {reason,topics}; preferred_time stores Morning/Afternoon/Evening.
function decodeTopic($topic)
{
    $v = json_decode($topic, true);
    return is_array($v) && isset($v['reason']) && is_array(isset($v['topics']) ? $v['topics'] : null) ? $v : array('reason' => $topic, 'topics' => array());
}

function validateSubmission($input, $requireStudent = true)
{
    $errors = array();
    $courses = array('IS 6465', 'IS 6495', 'IS 6570');
    $name = trim((string)(isset($input['student_name']) ? $input['student_name'] : ''));
    $email = trim((string)(isset($input['student_email']) ? $input['student_email'] : ''));
    $course = trim((string)(isset($input['course']) ? $input['course'] : ''));
    $reason = trim((string)(isset($input['reason']) ? $input['reason'] : ''));
    $date = (string)(isset($input['preferreddate']) ? $input['preferreddate'] : '');
    $time = (string)(isset($input['preferredtime']) ? $input['preferredtime'] : '');
    $topics = isset($input['topics']) ? $input['topics'] : array();
    if (!is_array($topics)) {
        $errors[] = 'Topics must be a list.';
        $topics = array();
    }
    if ($requireStudent && $name === '') {
        $errors[] = 'Student name is required.';
    }
    if ($requireStudent && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid student email required.';
    }
    if (!in_array($course, $courses, true)) {
        $errors[] = 'Select a valid course.';
    }
    if ($msg = validatePreferredTime($time)) {
        $errors[] = $msg;
    }
    if ($msg = validateTopics($topics)) {
        $errors[] = $msg;
    }
    if ($msg = validateReason($reason)) {
        $errors[] = $msg;
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date || $dt < new DateTimeImmutable('today')) {
        $errors[] = 'Choose a valid date today or later.';
    }
    $payload = array(
        'student_name' => $name,
        'student_email' => $email,
        'course' => $course,
        'topic' => json_encode(array('reason' => $reason, 'topics' => array_values($topics))),
        'preferred_date' => $date,
        'preferred_time' => $time,
    );
    return array($errors, $payload);
}

// Who may touch which requests. Returns the user_id to restrict by, or null for "all".
// Anyone who is neither Student nor Administrator is refused outright.
function requestOwnerScope($actor)
{
    if (is_array($actor) && isset($actor['role'])) {
        if ($actor['role'] === ROLE_STUDENT) {
            return (int)$actor['user_id'];
        }
        if ($actor['role'] === ROLE_ADMIN) {
            return null;
        }
    }
    throw new PermissionDeniedException('You are not authorized to perform this action.');
}

// UC01: only a Student submits. The name and email on the request come from the
// authenticated account; anything posted in student_name / student_email is overwritten.
// The student row and the request are written in one transaction.
function submitRequest($pdo, $input, $actor)
{
    if (!is_array($actor) || !isset($actor['role']) || $actor['role'] !== ROLE_STUDENT) {
        throw new PermissionDeniedException('Only students can submit tutoring requests.');
    }
    $input['student_name'] = $actor['full_name'];
    $input['student_email'] = $actor['email'];
    list($errors, $data) = validateSubmission($input);
    if ($errors) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }
    $userId = (int)$actor['user_id'];
    $pdo->beginTransaction();
    try {
        $student = findStudentByUserId($pdo, $userId);
        if (!$student) {
            try {
                $studentId = insertStudentForUser($pdo, $userId, $data['student_name'], $data['student_email']);
            } catch (PDOException $e) {
                if ((string)$e->getCode() === '23000') {
                    // A Lab 6 student row with this email exists but is not linked to any
                    // account. It is NOT claimed automatically: the email is unverified.
                    throw new ConflictException('A student record with your email already exists and is not linked to your account. Please ask an administrator for help.');
                }
                throw $e;
            }
        } else {
            $studentId = (int)$student['student_id'];
        }
        $id = insertTutoringRequest($pdo, $studentId, $data);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// Visible request or null. A Student asking for someone else's request gets the same
// null as for a request that does not exist (handout: do not reveal inaccessible data).
function getRequestForActor($pdo, $actor, $id)
{
    $owner = requestOwnerScope($actor);
    $row = ($owner === null) ? findRequest($pdo, $id) : findRequestForUser($pdo, $id, $owner);
    return $row ? $row : null;
}

function listRequestsForActor($pdo, $actor, $course = '', $status = '')
{
    return listRequests($pdo, $course, $status, requestOwnerScope($actor));
}

// Edit an Open request. Returns false if it is not found, not owned, or not Open.
function reviseRequest($pdo, $id, $input, $actor)
{
    $owner = requestOwnerScope($actor);
    list($errors, $data) = validateSubmission($input, false);
    if ($errors) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }
    return updateOpenRequest($pdo, $id, $data, $owner);
}

// Cancel (status change, record retained). False if not found, not owned, or not Open.
function cancelRequest($pdo, $id, $actor)
{
    return cancelOpenRequest($pdo, $id, requestOwnerScope($actor));
}
