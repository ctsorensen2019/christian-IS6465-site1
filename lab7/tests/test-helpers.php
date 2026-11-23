<?php
// Shared test harness (CLI only). Used by integration-tests.php and user-management-tests.php.
//
// Rules this harness follows (Test Harness Generator skill):
//  * tests call the REAL application functions;
//  * every test uses a throw-away in-memory SQLite database built from the approved
//    schema file, so the working database/tutoring.db is never touched;
//  * "PASS" is printed only after a real condition has been evaluated;
//  * the process exits 0 only when every check passed, non-zero otherwise.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/services/userService.php';
require_once dirname(__DIR__) . '/services/requestService.php';

$passed = 0;
$failed = 0;

// Any PHP warning/notice or uncaught exception is a test failure, never a silent pass.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function ($e) {
    global $failed;
    $failed++;
    echo 'FAIL: uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' . PHP_EOL;
    finishTests();
});

function check($condition, $label)
{
    global $passed, $failed;
    if ($condition === true) {
        $passed++;
        echo 'PASS: ' . $label . PHP_EOL;
    } else {
        $failed++;
        echo 'FAIL: ' . $label . PHP_EOL;
    }
}

function section($title)
{
    echo PHP_EOL . '== ' . $title . ' ==' . PHP_EOL;
}

// Fresh, isolated database with the approved schema (no sample rows).
function newTestDatabase()
{
    $pdo = openDatabase(':memory:');
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/setup-database.sql'));
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/upgrade-to-user-management.sql'));
    return $pdo;
}

// Run $fn and return the exception it threw (or null if it did not throw).
function caught($fn)
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e;
    }
    return null;
}

function isA($e, $class)
{
    return $e instanceof $class;
}

function countRows($pdo, $table)
{
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

// Create an account directly through the repository (fixtures only). The password is
// hashed with password_hash exactly as the application does.
function makeUser($pdo, $name, $email, $role, $status = 'Active', $password = 'Passw0rd1')
{
    $id = insertUser($pdo, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $status);
    return findUserById($pdo, $id);
}

function validRequestInput($overrides = array())
{
    return array_merge(array(
        'course' => 'IS 6465',
        'reason' => 'Please help with PHP form validation.',
        'topics' => array('Exam review', 'Study plan'),
        'preferreddate' => (new DateTimeImmutable('+10 days'))->format('Y-m-d'),
        'preferredtime' => 'Morning',
    ), $overrides);
}

// Controlled negative test: proves the harness can actually fail.
//   php tests/<file>.php --negative-control      (expected exit status: 1)
function runNegativeControlIfRequested()
{
    global $argv;
    if (isset($argv) && in_array('--negative-control', $argv, true)) {
        check(false, 'NEGATIVE CONTROL: this check is designed to fail');
        finishTests();
    }
}

function finishTests()
{
    global $passed, $failed;
    echo PHP_EOL . "Results: $passed passed, $failed failed" . PHP_EOL;
    exit($failed > 0 ? 1 : 0);
}
