<?php
// Create an Administrator account from the command line. Public registration can never
// create administrators, so this is how the first one is made.
//
//   cd database
//   php create-admin.php "Test Administrator" admin@example.com "TestPassword123"
//
// Arguments: full name, email, password (at least 8 characters with a letter and a digit;
// the lab asks for 12 or more). If the password is left out it is prompted for twice
// (hidden in a terminal) or read from the CREATE_ADMIN_PASSWORD environment variable, which
// keeps it out of shell history. Name and email are prompted for when left out.
// Optional: --db=/path/to/other.db. It refuses to touch an existing account, and the
// users table must exist first (run upgrade-to-user-management.sql).

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/validation.php';
require_once dirname(__DIR__) . '/repositories/userPDO.php';

function fail($message, $code = 1)
{
    fwrite(STDERR, $message . "\n");
    exit($code);
}

function ask($label, $hidden = false)
{
    echo $label;
    $tty = function_exists('stream_isatty') && stream_isatty(STDIN);
    if ($hidden && $tty) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($hidden && $tty) {
        shell_exec('stty echo');
        echo "\n";
    }
    return $line === false ? '' : rtrim($line, "\r\n");
}

$path = null;
$positional = array();
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--db=') === 0) {
        $path = substr($arg, 5);
    } elseif (strpos($arg, '--') === 0) {
        fail("Unknown option: $arg\nUsage: php create-admin.php \"Full Name\" email@example.com \"Password\" [--db=path]", 2);
    } else {
        $positional[] = $arg;
    }
}
if (count($positional) > 3) {
    fail("Too many arguments.\nUsage: php create-admin.php \"Full Name\" email@example.com \"Password\" [--db=path]", 2);
}
$name = isset($positional[0]) ? $positional[0] : '';
$email = isset($positional[1]) ? $positional[1] : '';
$password = isset($positional[2]) ? $positional[2] : false;

try {
    $pdo = openDatabase($path);
    $hasUsers = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn();
    if (!$hasUsers) {
        fail('The users table does not exist. Run database/upgrade-to-user-management.sql (Lab 6 database) or php database/setup-database.php first.');
    }

    if ($name === '') {
        $name = ask('Full name: ');
    }
    if ($email === '') {
        $email = ask('Email: ');
    }
    $name = trim($name);
    $email = strtolower(trim($email));

    if ($password === false || $password === '') {
        $password = getenv('CREATE_ADMIN_PASSWORD');
    }
    if ($password === false || $password === '') {
        $password = ask('Password: ', true);
        $confirm = ask('Confirm password: ', true);
        if ($msg = validatePasswordConfirmation($password, $confirm)) {
            fail($msg);
        }
    }

    foreach (array(validateFullName($name), validateEmail($email), validatePassword($password)) as $msg) {
        if ($msg !== '') {
            fail($msg);
        }
    }
    if (findUserByEmail($pdo, $email)) {
        fail("An account with $email already exists. Nothing was changed.");
    }

    insertUser($pdo, $name, $email, password_hash($password, PASSWORD_DEFAULT), ROLE_ADMIN, STATUS_ACTIVE);
    echo "Administrator account created for $email (role: Administrator)\n";
} catch (Throwable $e) {
    fail('Could not create the administrator: ' . $e->getMessage());
}
