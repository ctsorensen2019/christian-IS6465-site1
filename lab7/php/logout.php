<?php
// UM-10 Log Out. State-changing, so it is POST + CSRF token (the nav bar button).
// A plain GET never logs anyone out; it just sends the visitor to the login page.
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('login.php', 302);
}

// Failure path "already expired": no authenticated session exists any more.
if (empty($_SESSION['user_id'])) {
    logoutSession();
    redirectTo('login.php?expired=1');
}

verifyCsrfToken($_POST);
logoutSession();                              // clear data, expire cookie, destroy session
redirectTo('login.php?logged_out=1');
