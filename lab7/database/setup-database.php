<?php
// Command-line setup / migration for the Lab 7 database.
//
//   php database/setup-database.php                     create or upgrade database/tutoring.db
//   php database/setup-database.php --demo              ... and add fictional demo accounts
//   php database/setup-database.php --db=/tmp/test.db   use another file
//
// It (1) applies setup-database.sql, (2) applies upgrade-to-user-management.sql when the
// database does not have user accounts yet (no student or request rows are lost), and
// (3) with --demo creates
// demo accounts whose passwords are hashed with password_hash() at run time (no hash is
// stored in source control). The router blocks /database/ over HTTP; this file is CLI only.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/validation.php';
require_once dirname(__DIR__) . '/repositories/userPDO.php';
require_once dirname(__DIR__) . '/repositories/studentPDO.php';

$path = dirname(__DIR__) . '/database/tutoring.db';
$demo = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--demo') {
        $demo = true;
    } elseif (strpos($arg, '--db=') === 0) {
        $path = substr($arg, 5);
    } else {
        fwrite(STDERR, "Unknown option: $arg\n");
        exit(2);
    }
}

function studentsHasUserColumn($pdo)
{
    foreach ($pdo->query('PRAGMA table_info(students)') as $column) {
        if ($column['name'] === 'user_id') {
            return true;
        }
    }
    return false;
}

try {
    $pdo = openDatabase($path);
    $pdo->exec(file_get_contents(__DIR__ . '/setup-database.sql'));

    // Add the Lab 7 user accounts (users table + students.user_id) unless already present.
    if (!studentsHasUserColumn($pdo)) {
        $pdo->exec(file_get_contents(__DIR__ . '/upgrade-to-user-management.sql'));
        if ($pdo->query('PRAGMA foreign_key_check')->fetchAll()) {
            throw new RuntimeException('Foreign key check failed after the upgrade.');
        }
        echo "Applied upgrade-to-user-management.sql (users table and students.user_id).\n";
    }

    if ($demo) {
        // Fictional accounts for the lab. Passwords are listed in README.md.
        $accounts = array(
            array('Avery Admin',   'admin@example.edu',        'Admin123!',    ROLE_ADMIN,   STATUS_ACTIVE),
            array('Taylor Lee',    'tutor@example.edu',        'Tutor123!',    ROLE_TUTOR,   STATUS_ACTIVE),
            array('Maria Lopez',   'maria.lopez@example.com',  'Student123!',  ROLE_STUDENT, STATUS_ACTIVE),
            array('Alex Morgan',   'alex@example.edu',         'Student123!',  ROLE_STUDENT, STATUS_ACTIVE),
            array('Jordan Kim',    'jordan.kim@example.edu',   'Student123!',  ROLE_STUDENT, STATUS_ACTIVE),
            array('Casey Inactive', 'inactive@example.edu',    'Student123!',  ROLE_STUDENT, STATUS_INACTIVE),
        );
        foreach ($accounts as $a) {
            if (findUserByEmail($pdo, $a[1])) {
                echo "Skipped (exists): $a[1]\n";
                continue;
            }
            $userId = insertUser($pdo, $a[0], $a[1], password_hash($a[2], PASSWORD_DEFAULT), $a[3], $a[4]);
            echo "Created $a[3] account: $a[1]\n";
            // Trusted, operator-run link: attach an existing Lab 6 student row with the same
            // email to this account so its requests stay visible. (The web app never does
            // this automatically because self-registered emails are unverified.)
            if ($a[3] === ROLE_STUDENT) {
                $legacy = findStudentByEmail($pdo, $a[1]);
                if ($legacy && $legacy['user_id'] === null) {
                    $stmt = $pdo->prepare('UPDATE students SET user_id = ? WHERE student_id = ?');
                    $stmt->execute(array($userId, $legacy['student_id']));
                    echo "  linked existing Lab 6 student record #{$legacy['student_id']}\n";
                }
            }
        }
    }
    echo "Database ready: $path\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Setup failed: ' . $e->getMessage() . "\n");
    exit(1);
}
