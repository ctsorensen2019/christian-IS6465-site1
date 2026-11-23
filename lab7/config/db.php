<?php
// Reusable PDO connection. Every repository and service receives this connection as a
// parameter; no other file opens the database.
//
// Path rules:
//   1. an explicit $path argument (used by tests and the setup script), else
//   2. the TUTORING_DB environment variable (used by tests/e2e-http-tests.sh so that
//      automated tests never touch the working database), else
//   3. database/tutoring.db
function openDatabase($path = null) {
    if ($path === null) {
        $env = getenv('TUTORING_DB');
        $path = ($env !== false && $env !== '') ? $env : dirname(__DIR__) . '/database/tutoring.db';
    }
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}
