<?php
// Automated tests for the User Management use cases UM-01 .. UM-10.
// Run from the lab7 folder:   php tests/user-management-tests.php
//
// Expected results come from the design document's use case steps, failure outcomes,
// business rules and acceptance tests (section "Traceability and acceptance tests"), and
// from the Authentication Handout. Test IDs are listed in docs/TRACEABILITY.md.
// UM-10 (logout) needs real cookies and sessions, so it is verified by
// tests/e2e-http-tests.sh; only its pure helper functions are covered here.

require_once __DIR__ . '/test-helpers.php';
runNegativeControlIfRequested();

function regInput($overrides = array())
{
    return array_merge(array(
        'full_name' => 'Priya Shah',
        'email' => 'priya@example.edu',
        'password' => 'Secret123',
        'confirm_password' => 'Secret123',
        'role' => 'Student',
    ), $overrides);
}

// ============================================================== UM-01 ==========
section('UM-01 Create User Account (WF-01)');
$pdo = newTestDatabase();

$id = registerUser($pdo, regInput());
$row = findUserById($pdo, $id);
check($id > 0 && $row['role'] === 'Student' && $row['status'] === 'Active', 'UM-01-T01 valid Student registration creates an Active Student');

$tid = registerUser($pdo, regInput(array('full_name' => 'Taylor Lee', 'email' => 'taylor@example.edu', 'role' => 'Tutor')));
$trow = findUserById($pdo, $tid);
check($trow['role'] === 'Tutor' && $trow['status'] === 'Active', 'UM-01-T02 valid Tutor registration creates an Active Tutor');

$auth = findUserAuthById($pdo, $id);
$leak = false;
foreach ($pdo->query('SELECT * FROM users')->fetchAll() as $u) {
    foreach ($u as $value) {
        if ($value === 'Secret123') {
            $leak = true;
        }
    }
}
check(password_verify('Secret123', $auth['password_hash']) && $auth['password_hash'] !== 'Secret123' && !$leak, 'UM-01-T03 only a password hash is stored (plain password appears in no column)');

$columns = array();
foreach ($pdo->query('PRAGMA table_info(users)') as $c) {
    $columns[] = $c['name'];
}
check($columns === array('user_id', 'full_name', 'email', 'password_hash', 'role', 'status', 'created_at'), 'UM-01-T04 users table has the approved columns and no confirmation/current password column');

$before = countRows($pdo, 'users');
$e = caught(function () use ($pdo) { registerUser($pdo, regInput()); });
check(isA($e, ValidationException::class) && countRows($pdo, 'users') === $before, 'UM-01-T05 duplicate email rejected, no row added');

$e = caught(function () use ($pdo) { registerUser($pdo, regInput(array('email' => '  PRIYA@Example.EDU '))); });
check(isA($e, ValidationException::class) && countRows($pdo, 'users') === $before, 'UM-01-T06 duplicate email rejected regardless of case and spaces');

$mid = registerUser($pdo, regInput(array('email' => 'Mixed.Case@Example.EDU', 'full_name' => 'Mixed Case')));
check(findUserById($pdo, $mid)['email'] === 'mixed.case@example.edu', 'UM-01-T07 email is stored trimmed and lower-case');

$before = countRows($pdo, 'users');
$e = caught(function () use ($pdo) { registerUser($pdo, regInput(array('email' => 'root@example.edu', 'role' => 'Administrator'))); });
check(isA($e, PermissionDeniedException::class) && countRows($pdo, 'users') === $before, 'UM-01-T08 Administrator role through public registration is denied, no row added');

foreach (array('Janitor', '', array('Administrator')) as $i => $badRole) {
    $e = caught(function () use ($pdo, $badRole) { registerUser($pdo, regInput(array('email' => 'bad@example.edu', 'role' => $badRole))); });
    check(isA($e, ValidationException::class) && countRows($pdo, 'users') === $before, 'UM-01-T09.' . ($i + 1) . ' unknown / empty / array role rejected, no row added');
}

$invalid = array(
    'missing name' => array(array('full_name' => '   '), 'Full name'),
    'invalid email' => array(array('email' => 'not-an-email'), 'valid email'),
    'too-short password' => array(array('password' => 'ab1', 'confirm_password' => 'ab1'), 'Password must'),
    'password without digit' => array(array('password' => 'Password', 'confirm_password' => 'Password'), 'Password must'),
    'password without letter' => array(array('password' => '12345678', 'confirm_password' => '12345678'), 'Password must'),
    'mismatched confirmation' => array(array('confirm_password' => 'Different1'), 'do not match'),
);
foreach ($invalid as $label => $case) {
    $e = caught(function () use ($pdo, $case) { registerUser($pdo, regInput(array_merge(array('email' => 'new@example.edu'), $case[0]))); });
    check(isA($e, ValidationException::class) && strpos($e->getMessage(), $case[1]) !== false && countRows($pdo, 'users') === $before, 'UM-01-T10 ' . $label . ' rejected with a correction message, no row added');
}

check(authenticateUser($pdo, 'priya@example.edu', 'Secret123') !== null, 'UM-01-T11 new account is Active immediately (can log in)');

$evil = "Robert'); DROP TABLE users;--";
$eid = registerUser($pdo, regInput(array('full_name' => $evil, 'email' => 'bobby@example.edu')));
check(findUserById($pdo, $eid)['full_name'] === $evil && countRows($pdo, 'users') > 3, 'UM-01-T12 SQL metacharacters are stored as data (prepared statements)');

// ============================================================== UM-02 ==========
section('UM-02 View Own Account (WF-02)');
$pdo = newTestDatabase();
$alex = makeUser($pdo, 'Alex Morgan', 'alex@example.edu', 'Student');
$sam = makeUser($pdo, 'Sam Park', 'sam@example.edu', 'Tutor');

$p = getOwnProfile($pdo, $alex);
check($p['full_name'] === 'Alex Morgan' && $p['email'] === 'alex@example.edu' && $p['role'] === 'Student' && $p['status'] === 'Active', 'UM-02-T01 profile shows own name, email, role and status');
check(!array_key_exists('password_hash', $p), 'UM-02-T02 profile never includes the password hash');
$ps = getOwnProfile($pdo, $sam);
check($ps['email'] === 'sam@example.edu' && $p['email'] !== $ps['email'], 'UM-02-T03 each user sees only their own profile');

$now = time();
check(evaluateSession($pdo, array('user_id' => $alex['user_id'], 'last_activity' => $now - 1201), $now)['status'] === 'expired', 'UM-02-T04 session idle for more than 20 minutes is expired');
check(evaluateSession($pdo, array('user_id' => $alex['user_id'], 'last_activity' => $now - 1200), $now)['status'] === 'ok', 'UM-02-T05 session idle for exactly 20 minutes is still valid');
check(evaluateSession($pdo, array(), $now)['status'] === 'anonymous', 'UM-02-T06 no session user means anonymous (redirect to login)');
check(evaluateSession($pdo, array('user_id' => $alex['user_id']), $now)['status'] === 'expired', 'UM-02-T07 session with no activity time is treated as expired');

// ============================================================== UM-03 ==========
section('UM-03 View and Search User Accounts (WF-03)');
$pdo = newTestDatabase();
$admin = makeUser($pdo, 'Avery Admin', 'admin@example.edu', 'Administrator');
$alex = makeUser($pdo, 'Alex Morgan', 'alex@example.edu', 'Student');
$tay = makeUser($pdo, 'Taylor Lee', 'taylor@school.org', 'Tutor');
$cas = makeUser($pdo, 'Casey Inactive', 'casey@example.edu', 'Student', 'Inactive');
$pct = makeUser($pdo, '100% Real', 'real@example.edu', 'Student');
$none = array('q' => '', 'role' => '', 'status' => '');

check(count(searchUserAccounts($pdo, $admin, $none)) === 5, 'UM-03-T01 no filters lists every account');
$r = searchUserAccounts($pdo, $admin, array('q' => 'ALEX', 'role' => '', 'status' => ''));
check(count($r) === 1 && $r[0]['email'] === 'alex@example.edu', 'UM-03-T02 text search matches name, case-insensitively');
$r = searchUserAccounts($pdo, $admin, array('q' => 'school.org', 'role' => '', 'status' => ''));
check(count($r) === 1 && $r[0]['full_name'] === 'Taylor Lee', 'UM-03-T03 text search matches email');
$r = searchUserAccounts($pdo, $admin, array('q' => '', 'role' => 'Tutor', 'status' => ''));
check(count($r) === 1 && $r[0]['role'] === 'Tutor', 'UM-03-T04 role filter');
$r = searchUserAccounts($pdo, $admin, array('q' => '', 'role' => '', 'status' => 'Inactive'));
check(count($r) === 1 && $r[0]['email'] === 'casey@example.edu', 'UM-03-T05 status filter');
$r = searchUserAccounts($pdo, $admin, array('q' => 'example.edu', 'role' => 'Student', 'status' => 'Active'));
check(count($r) === 2, 'UM-03-T06 text, role and status filters combine (Alex and 100% Real)');
check(searchUserAccounts($pdo, $admin, array('q' => 'zzz-no-match', 'role' => '', 'status' => '')) === array(), 'UM-03-T07 no matches returns an empty list');
$r = searchUserAccounts($pdo, $admin, array('q' => '%', 'role' => '', 'status' => ''));
check(count($r) === 1 && $r[0]['full_name'] === '100% Real', 'UM-03-T08 % in the search box is matched literally, not as a wildcard');
check(searchUserAccounts($pdo, $admin, array('q' => '_', 'role' => '', 'status' => '')) === array(), 'UM-03-T09 _ in the search box is matched literally');
foreach (array('Student' => $alex, 'Tutor' => $tay) as $roleName => $actor) {
    $e = caught(function () use ($pdo, $actor, $none) { searchUserAccounts($pdo, $actor, $none); });
    check(isA($e, PermissionDeniedException::class), "UM-03-T10 $roleName is denied account search");
}
$e = caught(function () use ($pdo, $admin) { searchUserAccounts($pdo, $admin, array('q' => '', 'role' => 'Root', 'status' => '')); });
check(isA($e, ValidationException::class), 'UM-03-T11 invalid role filter rejected');
$e = caught(function () use ($pdo, $admin) { searchUserAccounts($pdo, $admin, array('q' => '', 'role' => '', 'status' => 'Banned')); });
check(isA($e, ValidationException::class), 'UM-03-T12 invalid status filter rejected');
$leak = false;
foreach (searchUserAccounts($pdo, $admin, $none) as $row) {
    if (array_key_exists('password_hash', $row)) {
        $leak = true;
    }
}
check(!$leak, 'UM-03-T13 search results never include password hashes');

$stu = $alex;
submitRequest($pdo, validRequestInput(), $stu);
submitRequest($pdo, validRequestInput(array('course' => 'IS 6495')), $stu);
$d = getUserForAdministrator($pdo, $admin, $stu['user_id']);
check($d['email'] === 'alex@example.edu' && $d['request_count'] === 2 && !array_key_exists('password_hash', $d), 'UM-03-T14 account details show permitted fields and related request count');
check(isA(caught(function () use ($pdo, $admin) { getUserForAdministrator($pdo, $admin, 9999); }), NotFoundException::class), 'UM-03-T15 details for a missing account -> not found');
check(isA(caught(function () use ($pdo, $stu) { getUserForAdministrator($pdo, $stu, 1); }), PermissionDeniedException::class), 'UM-03-T16 Student cannot open another account\'s details');

// ============================================================== UM-04 ==========
section('UM-04 Update Own Account (WF-02)');
$pdo = newTestDatabase();
$alex = makeUser($pdo, 'Alex Morgan', 'alex@example.edu', 'Student');
$sam = makeUser($pdo, 'Sam Park', 'sam@example.edu', 'Student');

check(updateOwnProfile($pdo, $alex, array('full_name' => 'Alexandra Morgan', 'email' => 'Alexandra@Example.edu')) === true, 'UM-04-T01 valid name and email update succeeds');
$a = findUserById($pdo, $alex['user_id']);
check($a['full_name'] === 'Alexandra Morgan' && $a['email'] === 'alexandra@example.edu', 'UM-04-T02 new values persisted (email normalized)');
check($a['role'] === 'Student' && $a['status'] === 'Active', 'UM-04-T03 role and status unchanged by a profile edit');
check(findUserById($pdo, $sam['user_id'])['email'] === 'sam@example.edu', 'UM-04-T04 another user\'s account is untouched');

$alex = findUserById($pdo, $alex['user_id']);
$e = caught(function () use ($pdo, $alex) { updateOwnProfile($pdo, $alex, array('full_name' => 'Alexandra Morgan', 'email' => 'sam@example.edu')); });
check(isA($e, ValidationException::class) && findUserById($pdo, $alex['user_id'])['email'] === 'alexandra@example.edu', 'UM-04-T05 duplicate email rejected, account unchanged');
check(updateOwnProfile($pdo, $alex, array('full_name' => 'Alex M.', 'email' => 'alexandra@example.edu')) === true, 'UM-04-T06 keeping your own email while changing the name is not a duplicate');
foreach (array('bad email' => array('full_name' => 'Alex', 'email' => 'nope'), 'blank name' => array('full_name' => ' ', 'email' => 'ok@example.edu')) as $label => $in) {
    $e = caught(function () use ($pdo, $alex, $in) { updateOwnProfile($pdo, $alex, $in); });
    check(isA($e, ValidationException::class) && findUserById($pdo, $alex['user_id'])['full_name'] === 'Alex M.', "UM-04-T07 $label rejected, account unchanged");
}
foreach (array('role' => 'Administrator', 'status' => 'Inactive') as $field => $value) {
    $e = caught(function () use ($pdo, $alex, $field, $value) { updateOwnProfile($pdo, $alex, array('full_name' => 'Hacked Name', 'email' => 'hacked@example.edu', $field => $value)); });
    $a = findUserById($pdo, $alex['user_id']);
    check(isA($e, PermissionDeniedException::class) && $a[$field] === ($field === 'role' ? 'Student' : 'Active') && $a['full_name'] === 'Alex M.' && $a['email'] === 'alexandra@example.edu', "UM-04-T08 submitting $field is denied and nothing at all is changed");
}
updateOwnProfile($pdo, $alex, array('full_name' => 'Alex M.', 'email' => 'alexandra@example.edu', 'user_id' => $sam['user_id']));
check(findUserById($pdo, $sam['user_id'])['full_name'] === 'Sam Park', 'UM-04-T09 a forged user_id field cannot redirect the update to another account');

$stuAlex = findUserById($pdo, $alex['user_id']);
$rid = submitRequest($pdo, validRequestInput(), $stuAlex);
updateOwnProfile($pdo, $stuAlex, array('full_name' => 'Alex Renamed', 'email' => 'renamed@example.edu'));
$joined = findRequest($pdo, $rid);
check($joined['student_name'] === 'Alex Renamed' && $joined['student_email'] === 'renamed@example.edu', 'UM-04-T10 student profile row stays in sync so request lists show the new name');

// ============================================================== UM-05 ==========
section('UM-05 Change Password (WF-04)');
$pdo = newTestDatabase();
$alex = makeUser($pdo, 'Alex Morgan', 'alex@example.edu', 'Student', 'Active', 'OldPass123');
$sam = makeUser($pdo, 'Sam Park', 'sam@example.edu', 'Student', 'Active', 'SamPass123');
$samHash = findUserAuthById($pdo, $sam['user_id'])['password_hash'];
$oldHash = findUserAuthById($pdo, $alex['user_id'])['password_hash'];

$e = caught(function () use ($pdo, $alex) { changeOwnPassword($pdo, $alex, 'WrongOld1', 'NewPass456', 'NewPass456'); });
check(isA($e, ValidationException::class) && findUserAuthById($pdo, $alex['user_id'])['password_hash'] === $oldHash, 'UM-05-T01 incorrect current password rejected, hash unchanged');
$e = caught(function () use ($pdo, $alex) { changeOwnPassword($pdo, $alex, 'OldPass123', 'NewPass456', 'Mismatch456'); });
check(isA($e, ValidationException::class) && findUserAuthById($pdo, $alex['user_id'])['password_hash'] === $oldHash, 'UM-05-T02 mismatched confirmation rejected, hash unchanged');
$e = caught(function () use ($pdo, $alex) { changeOwnPassword($pdo, $alex, 'OldPass123', 'short', 'short'); });
check(isA($e, ValidationException::class) && findUserAuthById($pdo, $alex['user_id'])['password_hash'] === $oldHash, 'UM-05-T03 password that breaks the rules rejected, hash unchanged');
check(authenticateUser($pdo, 'alex@example.edu', 'OldPass123') !== null, 'UM-05-T04 after the failed attempts the old password still works');

check(changeOwnPassword($pdo, $alex, 'OldPass123', 'NewPass456', 'NewPass456') === true, 'UM-05-T05 valid change succeeds');
check(authenticateUser($pdo, 'alex@example.edu', 'NewPass456') !== null, 'UM-05-T06 the new password logs in');
check(authenticateUser($pdo, 'alex@example.edu', 'OldPass123') === null, 'UM-05-T07 the old password no longer works');
$newHash = findUserAuthById($pdo, $alex['user_id'])['password_hash'];
check($newHash !== $oldHash && $newHash !== 'NewPass456' && password_verify('NewPass456', $newHash), 'UM-05-T08 a new password hash is stored (never the plain password)');
check(findUserAuthById($pdo, $sam['user_id'])['password_hash'] === $samHash, 'UM-05-T09 another user\'s hash is untouched');

// ============================================================== UM-06 ==========
section('UM-06 Update User Role (WF-05)');
$pdo = newTestDatabase();
$admin = makeUser($pdo, 'Avery Admin', 'admin@example.edu', 'Administrator');
$stu = makeUser($pdo, 'Alex Morgan', 'alex@example.edu', 'Student');
$tut = makeUser($pdo, 'Taylor Lee', 'taylor@example.edu', 'Tutor');
$sid = (int)$stu['user_id'];

check(changeUserRole($pdo, $admin, $sid, 'Tutor') === true && findUserById($pdo, $sid)['role'] === 'Tutor', 'UM-06-T01 administrator changes Student to Tutor');
check(changeUserRole($pdo, $admin, $sid, 'Student') === true && findUserById($pdo, $sid)['role'] === 'Student', 'UM-06-T02 administrator changes it back');
foreach (array('Student' => $stu, 'Tutor' => $tut) as $roleName => $actor) {
    $e = caught(function () use ($pdo, $actor, $sid) { changeUserRole($pdo, $actor, $sid, 'Administrator'); });
    check(isA($e, PermissionDeniedException::class) && findUserById($pdo, $sid)['role'] === 'Student', "UM-06-T03 $roleName actor denied (privilege escalation blocked), role unchanged");
}
$e = caught(function () use ($pdo, $stu) { changeUserRole($pdo, $stu, (int)$stu['user_id'], 'Administrator'); });
check(isA($e, PermissionDeniedException::class) && findUserById($pdo, $sid)['role'] === 'Student', 'UM-06-T04 a user cannot promote themselves');
$e = caught(function () use ($pdo, $admin, $sid) { changeUserRole($pdo, $admin, $sid, 'Superuser'); });
check(isA($e, ValidationException::class) && findUserById($pdo, $sid)['role'] === 'Student', 'UM-06-T05 unknown role rejected');
check(isA(caught(function () use ($pdo, $admin) { changeUserRole($pdo, $admin, 9999, 'Tutor'); }), NotFoundException::class), 'UM-06-T06 missing account -> not found');
check(isA(caught(function () use ($pdo, $admin, $sid) { changeUserRole($pdo, $admin, $sid, 'Student'); }), ValidationException::class), 'UM-06-T07 "change" to the current role is rejected');
$e = caught(function () use ($pdo, $admin) { changeUserRole($pdo, $admin, (int)$admin['user_id'], 'Student'); });
check(isA($e, ConflictException::class) && findUserById($pdo, $admin['user_id'])['role'] === 'Administrator', 'UM-06-T08 the last active administrator cannot be demoted');
$admin2 = makeUser($pdo, 'Blake Boss', 'blake@example.edu', 'Administrator', 'Inactive');
$e = caught(function () use ($pdo, $admin) { changeUserRole($pdo, $admin, (int)$admin['user_id'], 'Tutor'); });
check(isA($e, ConflictException::class), 'UM-06-T09 an INACTIVE second administrator does not count as a replacement');
setUserStatus($pdo, $admin, (int)$admin2['user_id'], 'Active');
check(changeUserRole($pdo, $admin, (int)$admin['user_id'], 'Tutor') === true && findUserById($pdo, $admin['user_id'])['role'] === 'Tutor', 'UM-06-T10 demotion is allowed once another administrator is active');
$now = time();
$sess = evaluateSession($pdo, array('user_id' => $admin['user_id'], 'last_activity' => $now, 'role' => 'Administrator'), $now);
check($sess['user']['role'] === 'Tutor', 'UM-06-T11 a stale "Administrator" role in the session is overridden by the database role');

// ============================================================== UM-07 ==========
section('UM-07 Activate or Deactivate Account (WF-06)');
$pdo = newTestDatabase();
$admin = makeUser($pdo, 'Avery Admin', 'admin@example.edu', 'Administrator');
$stu = makeUser($pdo, 'Alex Morgan', 'alex@example.edu', 'Student', 'Active', 'Passw0rd1');
$oth = makeUser($pdo, 'Sam Park', 'sam@example.edu', 'Student');
$sid = (int)$stu['user_id'];

check(authenticateUser($pdo, 'alex@example.edu', 'Passw0rd1') !== null, 'UM-07-T01 precondition: active user can log in');
$now = time();
check(setUserStatus($pdo, $admin, $sid, 'Inactive') === true && findUserById($pdo, $sid)['status'] === 'Inactive', 'UM-07-T02 administrator deactivates the account');
check(authenticateUser($pdo, 'alex@example.edu', 'Passw0rd1') === null, 'UM-07-T03 inactive user cannot log in (correct password)');
check(evaluateSession($pdo, array('user_id' => $sid, 'last_activity' => $now), $now)['status'] === 'inactive', 'UM-07-T04 an already-open session of a deactivated user is ended on the next request');
check(findUserById($pdo, $oth['user_id'])['status'] === 'Active', 'UM-07-T05 other accounts are unaffected');
check(setUserStatus($pdo, $admin, $sid, 'Active') === true && authenticateUser($pdo, 'alex@example.edu', 'Passw0rd1') !== null, 'UM-07-T06 reactivation restores login');
$e = caught(function () use ($pdo, $oth, $sid) { setUserStatus($pdo, $oth, $sid, 'Inactive'); });
check(isA($e, PermissionDeniedException::class) && findUserById($pdo, $sid)['status'] === 'Active', 'UM-07-T07 non-administrator denied, status unchanged');
check(isA(caught(function () use ($pdo, $admin, $sid) { setUserStatus($pdo, $admin, $sid, 'Suspended'); }), ValidationException::class), 'UM-07-T08 unknown status rejected');
check(isA(caught(function () use ($pdo, $admin, $sid) { setUserStatus($pdo, $admin, $sid, 'Active'); }), ValidationException::class), 'UM-07-T09 Active -> Active is an invalid transition');
check(isA(caught(function () use ($pdo, $admin) { setUserStatus($pdo, $admin, 9999, 'Inactive'); }), NotFoundException::class), 'UM-07-T10 missing account -> not found');
$e = caught(function () use ($pdo, $admin) { setUserStatus($pdo, $admin, (int)$admin['user_id'], 'Inactive'); });
check(isA($e, ConflictException::class) && findUserById($pdo, $admin['user_id'])['status'] === 'Active', 'UM-07-T11 the last active administrator cannot be deactivated');

// ============================================================== UM-08 ==========
section('UM-08 Delete User Account (WF-07)');
$pdo = newTestDatabase();
$admin = makeUser($pdo, 'Avery Admin', 'admin@example.edu', 'Administrator');
$withReq = makeUser($pdo, 'Maria Lopez', 'maria@example.edu', 'Student');
$cancelled = makeUser($pdo, 'Casey Cancel', 'casey@example.edu', 'Student');
$clean = makeUser($pdo, 'Jordan Kim', 'jordan@example.edu', 'Student');
$tutor = makeUser($pdo, 'Taylor Lee', 'taylor@example.edu', 'Tutor');
$rid = submitRequest($pdo, validRequestInput(), $withReq);
$rid2 = submitRequest($pdo, validRequestInput(), $cancelled);
cancelRequest($pdo, $rid2, $cancelled);
insertStudentForUser($pdo, (int)$clean['user_id'], 'Jordan Kim', 'jordan@example.edu');   // empty student profile

$pv = previewAccountDeletion($pdo, $admin, (int)$withReq['user_id']);
check($pv['request_count'] === 1 && $pv['blocked_reason'] !== '', 'UM-08-T01 warning screen reports related requests and that deletion is blocked');
$pv = previewAccountDeletion($pdo, $admin, (int)$clean['user_id']);
check($pv['request_count'] === 0 && $pv['blocked_reason'] === '', 'UM-08-T02 warning screen reports no related requests');

$usersBefore = countRows($pdo, 'users');
$studentsBefore = countRows($pdo, 'students');
$reqBefore = countRows($pdo, 'tutoring_requests');
$e = caught(function () use ($pdo, $admin, $withReq) { deleteUserAccount($pdo, $admin, (int)$withReq['user_id']); });
check(isA($e, ConflictException::class) && strpos($e->getMessage(), 'related tutoring request') !== false, 'UM-08-T03 deletion blocked by the retention policy (message names the related tutoring requests, not just a database error)');
check(countRows($pdo, 'users') === $usersBefore && countRows($pdo, 'students') === $studentsBefore && countRows($pdo, 'tutoring_requests') === $reqBefore, 'UM-08-T04 a blocked deletion changes no rows (user, student and requests all retained)');
$e = caught(function () use ($pdo, $admin, $cancelled) { deleteUserAccount($pdo, $admin, (int)$cancelled['user_id']); });
check(isA($e, ConflictException::class) && findRequest($pdo, $rid2)['status'] === 'Cancelled', 'UM-08-T05 retained Cancelled requests also block deletion');
$e = caught(function () use ($pdo, $tutor, $clean) { deleteUserAccount($pdo, $tutor, (int)$clean['user_id']); });
check(isA($e, PermissionDeniedException::class) && findUserById($pdo, $clean['user_id']) !== false, 'UM-08-T06 non-administrator denied, account remains');
check(isA(caught(function () use ($pdo, $admin) { deleteUserAccount($pdo, $admin, 9999); }), NotFoundException::class), 'UM-08-T07 missing account -> not found');

check(deleteUserAccount($pdo, $admin, (int)$clean['user_id']) === true, 'UM-08-T08 account with no related requests is deleted');
check(findUserById($pdo, $clean['user_id']) === false && findStudentByUserId($pdo, (int)$clean['user_id']) === false, 'UM-08-T09 the user row and its empty student profile are gone');
check(countRows($pdo, 'users') === $usersBefore - 1 && countRows($pdo, 'tutoring_requests') === $reqBefore, 'UM-08-T10 no other user or request row was removed');
check(deleteUserAccount($pdo, $admin, (int)$tutor['user_id']) === true && findUserById($pdo, $tutor['user_id']) === false, 'UM-08-T11 an account that never had a student profile can be deleted');

$e = caught(function () use ($pdo, $admin) { deleteUserAccount($pdo, $admin, (int)$admin['user_id']); });
check(isA($e, ConflictException::class) && findUserById($pdo, $admin['user_id']) !== false, 'UM-08-T12 the last active administrator cannot be deleted');
$admin2 = makeUser($pdo, 'Blake Boss', 'blake@example.edu', 'Administrator');
check(deleteUserAccount($pdo, $admin, (int)$admin['user_id']) === true, 'UM-08-T13 an administrator can be deleted while another active administrator exists');

$pdo2 = newTestDatabase();
$s = makeUser($pdo2, 'Fk Check', 'fk@example.edu', 'Student');
submitRequest($pdo2, validRequestInput(), $s);
$e = caught(function () use ($pdo2, $s) { $pdo2->exec('DELETE FROM users WHERE user_id = ' . (int)$s['user_id']); });
check(isA($e, PDOException::class) && findUserById($pdo2, $s['user_id']) !== false, 'UM-08-T14 SQLite foreign keys independently refuse to orphan student/request rows');

// ============================================================== UM-09 ==========
section('UM-09 Log In (WF-08) and session helpers');
$pdo = newTestDatabase();
$alex = makeUser($pdo, 'Alex Morgan', 'alex@example.edu', 'Student', 'Active', 'Passw0rd1');
$inactive = makeUser($pdo, 'Casey Inactive', 'casey@example.edu', 'Student', 'Inactive', 'Passw0rd1');

$u = authenticateUser($pdo, 'alex@example.edu', 'Passw0rd1');
check($u !== null && (int)$u['user_id'] === (int)$alex['user_id'] && $u['role'] === 'Student' && !array_key_exists('password_hash', $u), 'UM-09-T01 valid credentials return the user (without the hash)');
check(authenticateUser($pdo, 'alex@example.edu', 'wrong-password1') === null, 'UM-09-T02 wrong password fails');
check(authenticateUser($pdo, 'nobody@example.edu', 'Passw0rd1') === null, 'UM-09-T03 unknown email fails');
check(authenticateUser($pdo, 'casey@example.edu', 'Passw0rd1') === null, 'UM-09-T04 inactive account fails even with the right password');
check(authenticateUser($pdo, '  ALEX@Example.EDU ', 'Passw0rd1') !== null, 'UM-09-T05 email is matched trimmed and case-insensitively');
check(authenticateUser($pdo, '', '') === null && authenticateUser($pdo, 'alex@example.edu', '') === null, 'UM-09-T06 empty credentials fail');
check(authenticateUser($pdo, 'alex@example.edu', 'passw0rd1') === null, 'UM-09-T07 passwords are case-sensitive');

$info = password_get_info(DUMMY_PASSWORD_HASH);
check($info['algoName'] === 'bcrypt', 'UM-09-T08 the dummy hash used for unknown emails is a valid bcrypt hash (so the timing is equalised)');

$weak = password_hash('Passw0rd1', PASSWORD_BCRYPT, array('cost' => 4));
$pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute(array($weak, $alex['user_id']));
check(authenticateUser($pdo, 'alex@example.edu', 'Passw0rd1') !== null, 'UM-09-T09 login with an old low-cost hash succeeds');
$upgraded = findUserAuthById($pdo, $alex['user_id'])['password_hash'];
check($upgraded !== $weak && !password_needs_rehash($upgraded, PASSWORD_DEFAULT) && password_verify('Passw0rd1', $upgraded), 'UM-09-T10 the stored hash is upgraded after a successful login (password_needs_rehash)');

$now = time();
$ok = evaluateSession($pdo, array('user_id' => $alex['user_id'], 'last_activity' => $now), $now);
check($ok['status'] === 'ok' && $ok['user']['email'] === 'alex@example.edu', 'UM-09-T11 a valid session resolves to the current user row');
$pdo->exec('DELETE FROM users WHERE user_id = ' . (int)$inactive['user_id']);
check(evaluateSession($pdo, array('user_id' => $inactive['user_id'], 'last_activity' => $now), $now)['status'] === 'inactive', 'UM-09-T12 a session for a deleted account is rejected');

// ============================================================== UM-10 (helpers) ==
section('UM-10 Log Out / CSRF / role helpers (pure functions; full logout flow is in e2e-http-tests.sh)');
check(csrfTokenMatches('abc123', 'abc123') === true, 'UM-10-T01 matching CSRF token accepted');
check(csrfTokenMatches('abc123', 'abc124') === false, 'UM-10-T02 different CSRF token rejected');
check(csrfTokenMatches('', '') === false && csrfTokenMatches('abc', '') === false && csrfTokenMatches('', 'abc') === false, 'UM-10-T03 missing CSRF token rejected (including empty == empty)');
check(csrfTokenMatches('abc', array('abc')) === false, 'UM-10-T04 non-string CSRF token rejected');
check(roleAllowed(array('role' => 'Administrator'), array('Administrator')) === true && roleAllowed(array('role' => 'Student'), array('Administrator')) === false, 'UM-10-T05 roleAllowed grants only listed roles');
check(roleAllowed(array('role' => 'administrator'), array('Administrator')) === false && roleAllowed(null, array('Administrator')) === false && roleAllowed(array(), array('Student')) === false, 'UM-10-T06 roleAllowed is exact-match and fails closed');

section('Validators');
check(validatePassword('Abcdefg1') === '' && validatePassword('Abcdef1') !== '', 'VAL-T01 password minimum length is 8');
check(validatePassword(str_repeat('a', 71) . '1') === '' && validatePassword(str_repeat('a', 72) . '1') !== '', 'VAL-T02 password maximum is 72 bytes (bcrypt limit)');
check(validateEmail('a@b.co') === '' && validateEmail('a@b') !== '' && validateEmail(str_repeat('a', 250) . '@b.com') !== '', 'VAL-T03 email format and 254-character limit');
check(validateFullName(str_repeat('n', 120)) === '' && validateFullName(str_repeat('n', 121)) !== '', 'VAL-T04 full name limit is 120');
check(validateRole('Administrator', selfRegistrationRoles()) !== '' && validateRole('Tutor', selfRegistrationRoles()) === '', 'VAL-T05 self-registration roles exclude Administrator');

finishTests();
