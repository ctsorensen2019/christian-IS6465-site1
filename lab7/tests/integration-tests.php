<?php
// Integration + regression tests for the tutoring-request workflow (UC01, UC02) with the
// Lab 7 role and ownership rules. Run from the lab7 folder:   php tests/integration-tests.php
//
// Part 1 keeps every Lab 6 check (adapted to the new signatures that take the signed-in
// user). Part 2 adds the access-matrix tests from the Authentication Handout, section 13.
// Each test builds on a disposable in-memory database; database/tutoring.db is never opened.

require_once __DIR__ . '/test-helpers.php';
runNegativeControlIfRequested();

// ---------------------------------------------------------------- fixtures ----
$pdo = newTestDatabase();
$admin = makeUser($pdo, 'Avery Admin', 'admin@example.edu', 'Administrator');
$jordan = makeUser($pdo, 'Jordan Lee', 'jordan@example.edu', 'Student');
$priya = makeUser($pdo, 'Priya Shah', 'priya@example.edu', 'Student');
$tutor = makeUser($pdo, 'Taylor Lee', 'taylor@example.edu', 'Tutor');
$sample = validRequestInput();

// ============================== Part 1: Lab 6 regression (UC01 / UC02) ==========
section('UC01 Request Tutoring (regression)');

$id = submitRequest($pdo, $sample, $jordan);
$r = findRequest($pdo, $id);
check($r !== false && $r['status'] === 'Open', 'UC01-T01 create request, default status Open');
check(decodeTopic($r['topic'])['topics'] === array('Exam review', 'Study plan') && decodeTopic($r['topic'])['reason'] === $sample['reason'], 'UC01-T02 Lab 4 checkbox topics and reason textarea preserved in the topic JSON');
$srow = findStudentByUserId($pdo, (int)$jordan['user_id']);
check($srow !== false && (int)$r['student_id'] === (int)$srow['student_id'] && $r['student_email'] === 'jordan@example.edu' && $r['student_name'] === 'Jordan Lee', 'UC01-T03 request linked to the student row of the signed-in account (JOIN returns name and email)');
$id2 = submitRequest($pdo, $sample, $jordan);
check(countRows($pdo, 'students') === 1, 'UC01-T04 a second request by the same user reuses the student row');
check(countRows($pdo, 'tutoring_requests') === 2, 'UC01-T05 two requests stored');

$bad = $sample;
$bad['reason'] = 'short';
$e = caught(function () use ($pdo, $bad, $jordan) { submitRequest($pdo, $bad, $jordan); });
check(isA($e, InvalidArgumentException::class), 'UC01-T06 invalid reason rejected');
$cases = array(
    'invalid course' => array('course' => 'IS 9999'),
    'missing course' => array('course' => ''),
    'invalid preferred time' => array('preferredtime' => 'Midnight'),
    'no topics selected' => array('topics' => array()),
    'altered topic value' => array('topics' => array('Free tutoring')),
    'past date' => array('preferreddate' => '2001-01-01'),
    'malformed date' => array('preferreddate' => '2030-02-31'),
);
foreach ($cases as $label => $override) {
    $e = caught(function () use ($pdo, $sample, $override, $jordan) { submitRequest($pdo, array_merge($sample, $override), $jordan); });
    check(isA($e, InvalidArgumentException::class), "UC01-T07 $label rejected");
}
check(countRows($pdo, 'tutoring_requests') === 2 && countRows($pdo, 'students') === 1, 'UC01-T08 invalid submissions write nothing');

$special = 'Mr. O\'Brien said "help" with <b>&amp; tags</b> please.';
$sid = submitRequest($pdo, array_merge($sample, array('reason' => $special)), $priya);
check(decodeTopic(findRequest($pdo, $sid)['topic'])['reason'] === $special, 'UC01-T09 quotes and HTML special characters round-trip exactly (output encoding happens on display)');
check(escapeHtml($special) === 'Mr. O&#039;Brien said &quot;help&quot; with &lt;b&gt;&amp;amp; tags&lt;/b&gt; please.', 'UC01-T10 escapeHtml encodes quotes, angle brackets and ampersands');

$edit = array_merge($sample, array('reason' => 'Updated request reason for the exam.'));
check(reviseRequest($pdo, $id, $edit, $jordan) === true, 'UC01-T11 edit an Open request');
check(decodeTopic(findRequest($pdo, $id)['topic'])['reason'] === $edit['reason'], 'UC01-T12 updated reason persisted');
$e = caught(function () use ($pdo, $id, $sample, $jordan) { reviseRequest($pdo, $id, array_merge($sample, array('reason' => 'x')), $jordan); });
check(isA($e, InvalidArgumentException::class) && decodeTopic(findRequest($pdo, $id)['topic'])['reason'] === $edit['reason'], 'UC01-T13 invalid edit rejected, stored request unchanged');

check(cancelRequest($pdo, $id, $jordan) === true, 'UC01-T14 cancel an Open request');
check(findRequest($pdo, $id)['status'] === 'Cancelled', 'UC01-T15 cancelled row is retained with status Cancelled');
check(cancelRequest($pdo, $id, $jordan) === false, 'UC01-T16 a second cancellation is rejected');
check(reviseRequest($pdo, $id, $edit, $jordan) === false, 'UC01-T17 a Cancelled request cannot be edited');
check(findRequest($pdo, $id2)['status'] === 'Open' && findRequest($pdo, $sid)['status'] === 'Open', 'UC01-T18 unrelated requests unchanged');
check(cancelRequest($pdo, 99999, $jordan) === false && getRequestForActor($pdo, $jordan, 99999) === null, 'UC01-T19 unknown request ID: nothing found, nothing changed');

$e = caught(function () use ($pdo) { $pdo->exec("INSERT INTO tutoring_requests(student_id,course,topic,preferred_date,preferred_time) VALUES(999,'IS 6465','x','2030-01-01','Morning')"); });
check(isA($e, PDOException::class), 'UC01-T20 foreign key enforced (unknown student_id rejected)');

// Transaction rollback: the student row and the request are written together.
$newcomer = makeUser($pdo, 'Nia Newcomer', 'nia@example.edu', 'Student');
$pdo->exec("CREATE TRIGGER fail_insert BEFORE INSERT ON tutoring_requests BEGIN SELECT RAISE(ABORT, 'forced failure'); END");
$studentsBefore = countRows($pdo, 'students');
$requestsBefore = countRows($pdo, 'tutoring_requests');
$e = caught(function () use ($pdo, $sample, $newcomer) { submitRequest($pdo, $sample, $newcomer); });
$pdo->exec('DROP TRIGGER fail_insert');
check($e !== null && countRows($pdo, 'students') === $studentsBefore && countRows($pdo, 'tutoring_requests') === $requestsBefore && !$pdo->inTransaction(), 'UC01-T21 forced failure rolls back the whole transaction (no student row, no request, no open transaction)');
check(is_int(submitRequest($pdo, $sample, $newcomer)), 'UC01-T22 after the failure the same user can submit normally');

section('UC02 View and Filter Requests (regression)');
$pdo = newTestDatabase();
$admin = makeUser($pdo, 'Avery Admin', 'admin@example.edu', 'Administrator');
$jordan = makeUser($pdo, 'Jordan Lee', 'jordan@example.edu', 'Student');
$priya = makeUser($pdo, 'Priya Shah', 'priya@example.edu', 'Student');
$tutor = makeUser($pdo, 'Taylor Lee', 'taylor@example.edu', 'Tutor');
$a1 = submitRequest($pdo, validRequestInput(array('course' => 'IS 6465')), $jordan);
$a2 = submitRequest($pdo, validRequestInput(array('course' => 'IS 6495')), $jordan);
$a3 = submitRequest($pdo, validRequestInput(array('course' => 'IS 6465')), $jordan);
$b1 = submitRequest($pdo, validRequestInput(array('course' => 'IS 6465')), $priya);
cancelRequest($pdo, $a3, $jordan);

check(count(listRequestsForActor($pdo, $admin)) === 4, 'UC02-T01 no filters returns all saved requests (administrator)');
check(count(listRequestsForActor($pdo, $admin, 'IS 6465', '')) === 3, 'UC02-T02 course filter');
check(count(listRequestsForActor($pdo, $admin, '', 'Cancelled')) === 1, 'UC02-T03 status filter');
check(count(listRequestsForActor($pdo, $admin, 'IS 6465', 'Open')) === 2, 'UC02-T04 combined course + status filter');
check(listRequestsForActor($pdo, $admin, 'IS 6570', '') === array(), 'UC02-T05 no match gives an empty list');
$rows = listRequestsForActor($pdo, $admin, 'IS 6495', '');
check(count($rows) === 1 && $rows[0]['student_name'] === 'Jordan Lee', 'UC02-T06 student information comes from the joined students table');
$sql = "IS 6465' OR '1'='1";
check(listRequestsForActor($pdo, $admin, $sql, '') === array(), 'UC02-T07 filter values are parameters (injection string matches nothing)');

// ============================== Part 2: Lab 7 access matrix ====================
section('Access matrix: submit (Student only)');
$before = countRows($pdo, 'tutoring_requests');
$studentsBefore = countRows($pdo, 'students');
check(isA(caught(function () use ($pdo, $tutor) { submitRequest($pdo, validRequestInput(), $tutor); }), PermissionDeniedException::class), 'AUTH-T01 Tutor cannot submit a request');
check(isA(caught(function () use ($pdo, $admin) { submitRequest($pdo, validRequestInput(), $admin); }), PermissionDeniedException::class), 'AUTH-T02 Administrator cannot submit a request');
check(isA(caught(function () use ($pdo) { submitRequest($pdo, validRequestInput(), null); }), PermissionDeniedException::class), 'AUTH-T03 no actor -> denied');
check(countRows($pdo, 'tutoring_requests') === $before && countRows($pdo, 'students') === $studentsBefore, 'AUTH-T04 denied submissions write nothing');

section('Access matrix: view and list');
$jl = listRequestsForActor($pdo, $jordan);
$pl = listRequestsForActor($pdo, $priya);
$onlyOwn = true;
foreach ($jl as $row) {
    if ($row['student_name'] !== 'Jordan Lee') {
        $onlyOwn = false;
    }
}
check(count($jl) === 3 && $onlyOwn, 'AUTH-T05 a student lists only their own requests');
check(count($pl) === 1 && $pl[0]['student_name'] === 'Priya Shah', 'AUTH-T06 another student sees only theirs');
check(count(listRequestsForActor($pdo, $jordan, 'IS 6465', 'Open')) === 1, 'AUTH-T07 filters apply inside the ownership restriction');
check(getRequestForActor($pdo, $jordan, $a1) !== null, 'AUTH-T08 a student can open their own request');
check(getRequestForActor($pdo, $jordan, $b1) === null, 'AUTH-T09 a student asking for someone else\'s request gets "not found" (no information leak)');
check(getRequestForActor($pdo, $admin, $a1) !== null && getRequestForActor($pdo, $admin, $b1) !== null, 'AUTH-T10 an administrator can open any request');
foreach (array('list' => function () use ($pdo, $tutor) { listRequestsForActor($pdo, $tutor); },
               'view' => function () use ($pdo, $tutor, $a1) { getRequestForActor($pdo, $tutor, $a1); }) as $label => $fn) {
    check(isA(caught($fn), PermissionDeniedException::class), "AUTH-T11 Tutor cannot $label requests");
}

section('Access matrix: edit and cancel');
$edit = validRequestInput(array('reason' => 'Edited by the permitted actor.'));
check(reviseRequest($pdo, $b1, $edit, $jordan) === false, 'AUTH-T12 a student cannot edit another student\'s Open request');
check(decodeTopic(findRequest($pdo, $b1)['topic'])['reason'] !== $edit['reason'], 'AUTH-T13 ...and the stored request is unchanged');
check(cancelRequest($pdo, $b1, $jordan) === false && findRequest($pdo, $b1)['status'] === 'Open', 'AUTH-T14 a student cannot cancel another student\'s Open request');
check(reviseRequest($pdo, $b1, $edit, $admin) === true && decodeTopic(findRequest($pdo, $b1)['topic'])['reason'] === $edit['reason'], 'AUTH-T15 an administrator can edit any Open request');
check(cancelRequest($pdo, $a1, $admin) === true && findRequest($pdo, $a1)['status'] === 'Cancelled', 'AUTH-T16 an administrator can cancel any Open request (record retained)');
check(reviseRequest($pdo, $a1, $edit, $admin) === false, 'AUTH-T17 an administrator still cannot edit a Cancelled request');
check(isA(caught(function () use ($pdo, $tutor, $edit, $a2) { reviseRequest($pdo, $a2, $edit, $tutor); }), PermissionDeniedException::class), 'AUTH-T18 Tutor cannot edit');
check(isA(caught(function () use ($pdo, $tutor, $a2) { cancelRequest($pdo, $a2, $tutor); }), PermissionDeniedException::class) && findRequest($pdo, $a2)['status'] === 'Open', 'AUTH-T19 Tutor cannot cancel, request unchanged');

section('Ownership enforced in SQL (repository level)');
check(findRequestForUser($pdo, $a2, (int)$priya['user_id']) === false, 'AUTH-T20 findRequestForUser returns nothing for a non-owner');
check(findRequestForUser($pdo, $a2, (int)$jordan['user_id']) !== false, 'AUTH-T21 findRequestForUser returns the owner\'s row');
$data = validateSubmission($edit, false)[1];
check(updateOpenRequest($pdo, $a2, $data, (int)$priya['user_id']) === false && cancelOpenRequest($pdo, $a2, (int)$priya['user_id']) === false && findRequest($pdo, $a2)['status'] === 'Open', 'AUTH-T22 UPDATE statements themselves refuse a non-owner, even if the service layer were bypassed');

section('Identity comes from the account, not the form');
$mallory = makeUser($pdo, 'Mallory Mal', 'mallory@example.edu', 'Student');
$spoofed = validRequestInput(array('student_name' => 'Priya Shah', 'student_email' => 'priya@example.edu'));
$mid = submitRequest($pdo, $spoofed, $mallory);
$mr = findRequest($pdo, $mid);
check($mr['student_email'] === 'mallory@example.edu' && $mr['student_name'] === 'Mallory Mal', 'AUTH-T23 posted student_name / student_email are ignored; the request belongs to the signed-in account');
check(count(listRequestsForActor($pdo, $priya)) === 1, 'AUTH-T24 the spoofed submission did not appear in the victim\'s list');

section('Legacy (unlinked) student rows');
$pdo->exec("INSERT INTO students (student_name, student_email) VALUES ('Legacy Lee', 'legacy@example.edu')");
$legacyUser = makeUser($pdo, 'Larry Legacy', 'legacy@example.edu', 'Student');
$before = countRows($pdo, 'tutoring_requests');
$e = caught(function () use ($pdo, $legacyUser) { submitRequest($pdo, validRequestInput(), $legacyUser); });
check(isA($e, ConflictException::class) && countRows($pdo, 'tutoring_requests') === $before && !$pdo->inTransaction(), 'AUTH-T25 a new account is NOT silently attached to an unlinked Lab 6 student row with the same email (unverified email)');
check(countRows($pdo, 'students') === countRows($pdo, 'students WHERE user_id IS NOT NULL') + 1, 'AUTH-T26 the legacy student row is left untouched');

section('Requirement checks on schema');
$fk = array();
foreach ($pdo->query('PRAGMA foreign_key_list(students)') as $f) {
    $fk[] = $f['table'] . '.' . $f['to'];
}
check($fk === array('users.user_id'), 'SCHEMA-T01 students.user_id references users.user_id');
$e = caught(function () use ($pdo, $jordan) { $pdo->prepare('INSERT INTO students (student_name, student_email, user_id) VALUES (?,?,?)')->execute(array('Dup', 'dup@example.edu', $jordan['user_id'])); });
check(isA($e, PDOException::class), 'SCHEMA-T02 one user cannot own two student rows (students.user_id is UNIQUE)');
$e = caught(function () use ($pdo) { $pdo->exec("INSERT INTO users (full_name,email,password_hash,role) VALUES ('X','x@example.edu','h','Wizard')"); });
check(isA($e, PDOException::class), 'SCHEMA-T03 users.role CHECK constraint rejects unknown roles');
$e = caught(function () use ($pdo) { $pdo->exec("INSERT INTO users (full_name,email,password_hash,role,status) VALUES ('X','y@example.edu','h','Student','Banned')"); });
check(isA($e, PDOException::class), 'SCHEMA-T04 users.status CHECK constraint rejects unknown statuses');
$e2 = caught(function () use ($pdo) { $pdo->exec("INSERT INTO users (full_name,email,password_hash,role) VALUES ('Dup','jordan@example.edu','h','Student')"); });
check(isA($e2, PDOException::class), 'SCHEMA-T05 users.email is UNIQUE');

finishTests();
