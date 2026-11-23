<?php
// Tests for database/upgrade-to-user-management.sql and database/create-admin.php.
//   php tests/database-scripts-tests.php
// Uses temporary database files only.

require_once __DIR__ . '/test-helpers.php';
runNegativeControlIfRequested();

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/lab7-dbscripts-' . bin2hex(random_bytes(4));
mkdir($tmp);

function runCreateAdmin($root, $args, $env)
{
    $cmd = $env . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/database/create-admin.php') . ' ' . $args . ' 2>&1 < /dev/null';
    exec($cmd, $out, $status);
    return array($status, implode("\n", $out));
}

section('upgrade-to-user-management.sql on a Lab 6 database');
$db = $tmp . '/lab6.db';
$pdo = openDatabase($db);
$pdo->exec("CREATE TABLE students (student_id INTEGER PRIMARY KEY, student_name TEXT NOT NULL, student_email TEXT NOT NULL UNIQUE);
CREATE TABLE tutoring_requests (request_id INTEGER PRIMARY KEY, student_id INTEGER NOT NULL, course TEXT NOT NULL, topic TEXT NOT NULL, preferred_date TEXT NOT NULL, preferred_time TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'Open' CHECK(status IN ('Open','Cancelled','Assigned','Completed')), created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(student_id) REFERENCES students(student_id));
INSERT INTO students (student_name, student_email) VALUES ('Maria Lopez', 'maria.lopez@example.com');
INSERT INTO tutoring_requests (student_id, course, topic, preferred_date, preferred_time) VALUES (1, 'IS 6465', 'PHP arrays and forms', '2026-11-03', 'Morning');");
$pdo->exec(file_get_contents($root . '/database/upgrade-to-user-management.sql'));
check(countRows($pdo, 'students') === 1 && countRows($pdo, 'tutoring_requests') === 1, 'UPG-T01 existing student and request rows are kept');
$cols = array();
foreach ($pdo->query('PRAGMA table_info(students)') as $c) { $cols[] = $c['name']; }
check($cols === array('student_id', 'student_name', 'student_email', 'user_id'), 'UPG-T02 students gains user_id');
check(findStudentByUserId($pdo, 1) === false && $pdo->query('SELECT user_id FROM students')->fetchColumn() === null, 'UPG-T03 existing students start unlinked (NULL)');
$fk = array();
foreach ($pdo->query('PRAGMA foreign_key_list(students)') as $f) { $fk[] = $f['table'] . '.' . $f['to']; }
check($fk === array('users.user_id'), 'UPG-T04 students.user_id references users.user_id');
$u1 = makeUser($pdo, 'A One', 'a@example.edu', 'Student');
$u2 = makeUser($pdo, 'B Two', 'b@example.edu', 'Student');
insertStudentForUser($pdo, (int)$u1['user_id'], 'A One', 'a@example.edu');
$e = caught(function () use ($pdo, $u1) { $pdo->prepare('INSERT INTO students (student_name, student_email, user_id) VALUES (?,?,?)')->execute(array('Dup', 'dup@example.edu', $u1['user_id'])); });
check(isA($e, PDOException::class), 'UPG-T05 one account cannot own two student rows (unique index)');
$e = caught(function () use ($pdo) { $pdo->exec("INSERT INTO users (full_name,email,password_hash,role) VALUES ('X','x@example.edu','h','Wizard')"); });
check(isA($e, PDOException::class), 'UPG-T06 role CHECK constraint present');
$e = caught(function () use ($pdo, $root) { $pdo->exec(file_get_contents($root . '/database/upgrade-to-user-management.sql')); });
check($e !== null && countRows($pdo, 'students') === 2 && countRows($pdo, 'users') === 2, 'UPG-T07 running the upgrade twice stops at the duplicate column and changes no data');
$rid = submitRequest($pdo, validRequestInput(), findUserById($pdo, $u2['user_id']));
check($rid > 0, 'UPG-T08 the application works on the upgraded database (student submits a request)');

section('create-admin.php');
$db2 = $tmp . '/admin.db';
$p2 = openDatabase($db2);
$p2->exec(file_get_contents($root . '/database/setup-database.sql'));
$p2->exec(file_get_contents($root . '/database/upgrade-to-user-management.sql'));
$env = 'CREATE_ADMIN_PASSWORD=' . escapeshellarg('Admin12345');
list($st, $out) = runCreateAdmin($root, '--db=' . escapeshellarg($db2) . ' ' . escapeshellarg('Avery Admin') . ' ' . escapeshellarg('Admin@Example.edu'), $env);
$row = findUserByEmail($p2, 'admin@example.edu');
check($st === 0 && $row && $row['role'] === 'Administrator' && $row['status'] === 'Active', 'ADM-T01 creates an Active Administrator (email normalised)');
check($row && password_verify('Admin12345', $row['password_hash']) && $row['password_hash'] !== 'Admin12345', 'ADM-T02 only a password hash is stored');
check(authenticateUser($p2, 'admin@example.edu', 'Admin12345') !== null, 'ADM-T03 the new administrator can log in');
list($st, $out) = runCreateAdmin($root, '--db=' . escapeshellarg($db2) . ' Again admin@example.edu', $env);
check($st !== 0 && countRows($p2, 'users') === 1, 'ADM-T04 an existing email is refused and nothing changes');
list($st, $out) = runCreateAdmin($root, '--db=' . escapeshellarg($db2) . ' Weak weak@example.edu', 'CREATE_ADMIN_PASSWORD=short');
check($st !== 0 && countRows($p2, 'users') === 1, 'ADM-T05 a password that breaks the rules is refused');
list($st, $out) = runCreateAdmin($root, '--db=' . escapeshellarg($db2) . ' Bad not-an-email', $env);
check($st !== 0 && countRows($p2, 'users') === 1, 'ADM-T06 an invalid email is refused');
$db3 = $tmp . '/nousers.db';
$p3 = openDatabase($db3);
$p3->exec('CREATE TABLE students (student_id INTEGER PRIMARY KEY)');
list($st, $out) = runCreateAdmin($root, '--db=' . escapeshellarg($db3) . ' A a@example.edu', $env);
check($st !== 0 && strpos($out, 'users table does not exist') !== false, 'ADM-T07 a database without the users table gives a clear message');
list($st, $out) = runCreateAdmin($root, '--db=' . escapeshellarg($db2) . ' ' . escapeshellarg('Pat Positional') . ' pat@example.edu ' . escapeshellarg('TestPassword123'), '');
$row = findUserByEmail($p2, 'pat@example.edu');
check($st === 0 && $row && $row['role'] === 'Administrator' && password_verify('TestPassword123', $row['password_hash']) && strpos($out, 'created') !== false, 'ADM-T08 the lab command form (name email password as arguments) works');

foreach (glob($tmp . '/*') as $f) { unlink($f); }
rmdir($tmp);
finishTests();
