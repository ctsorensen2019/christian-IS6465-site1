<?php
// Authentication, session, CSRF and role helpers (IS 6465 Authentication Handout,
// sections 3-4, 8-12, 16-17). Every protected page loads this file BEFORE any output so
// that redirect headers can still be sent.
//
// Browser -> page -> requireLogin() -> requireRole() -> verifyCsrfToken() -> service -> repository -> SQLite

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/validation.php';      // ROLE_* and STATUS_* constants
require_once dirname(__DIR__) . '/repositories/userPDO.php';

// 20 minutes of inactivity ends the session (handout section 10).
const SESSION_TIMEOUT_SECONDS = 1200;

// ---------------------------------------------------------------- session ----

// Configure the cookie BEFORE session_start (handout section 4).
function startSecureSession()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // A Codespaces / reverse-proxy URL is HTTPS even though PHP itself sees plain HTTP, so
    // also honour X-Forwarded-Proto. A forged header can only change the Secure flag on
    // the sender's own cookie; it grants nothing.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    // Strict mode: PHP refuses a session ID the server never issued (session fixation).
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(array(
        'lifetime' => 0,          // browser-session cookie
        'path' => '/',
        'secure' => $https,
        'httponly' => true,       // not readable by JavaScript
        'samesite' => 'Lax',
    ));
    session_start();
    if (!headers_sent()) {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
    }
}

// UM-09 step 3: the moment an anonymous visitor becomes an authenticated user.
function establishSession($user)
{
    startSecureSession();
    session_regenerate_id(true);                     // new ID: defeats session fixation
    $_SESSION['user_id'] = (int)$user['user_id'];    // smallest useful set of facts
    $_SESSION['display_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']);                  // fresh CSRF token for the new login
    csrfToken();
}

// UM-10: clear data, expire the cookie, destroy the server-side session (handout section 16).
function logoutSession()
{
    startSecureSession();
    $_SESSION = array();
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// Pure decision function (no headers, no exit) so tests can exercise it in the CLI.
// Returns array('status' => anonymous|expired|inactive|ok, 'user' => row|null).
// The user row is always reloaded from SQLite: role and status are never trusted from
// the session (handout section 9).
function evaluateSession($pdo, array $session, $now)
{
    if (empty($session['user_id'])) {
        return array('status' => 'anonymous', 'user' => null);
    }
    $last = isset($session['last_activity']) ? (int)$session['last_activity'] : 0;
    if ($now - $last > SESSION_TIMEOUT_SECONDS) {
        return array('status' => 'expired', 'user' => null);
    }
    $user = findUserById($pdo, (int)$session['user_id']);
    if (!$user || $user['status'] !== STATUS_ACTIVE) {
        return array('status' => 'inactive', 'user' => null);
    }
    return array('status' => 'ok', 'user' => $user);
}

// The reusable guard. Call it first on every protected page.
function requireLogin($pdo)
{
    startSecureSession();
    // Protected pages must not be served from the browser cache after logout (UM-10).
    header('Cache-Control: no-store');
    $result = evaluateSession($pdo, $_SESSION, time());
    if ($result['status'] === 'anonymous') {
        redirectTo('login.php', 302);
    }
    if ($result['status'] === 'expired') {
        logoutSession();
        redirectTo('login.php?expired=1', 302);
    }
    if ($result['status'] === 'inactive') {
        logoutSession();
        redirectTo('login.php?inactive=1', 302);
    }
    $user = $result['user'];
    $_SESSION['last_activity'] = time();             // refresh only after the session proved valid
    $_SESSION['display_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    return $user;
}

// ----------------------------------------------------------- authorization ----

// Pure check, easy to test.
function roleAllowed($user, array $allowedRoles)
{
    return is_array($user) && isset($user['role']) && in_array($user['role'], $allowedRoles, true);
}

// Authorization: authenticated but not permitted -> 403 and stop (handout section 12).
function requireRole($user, array $allowedRoles)
{
    if (!roleAllowed($user, $allowedRoles)) {
        renderErrorPage(403, 'Access denied', 'You are not authorized to perform this action.');
    }
}

// ------------------------------------------------------------------- CSRF ----

function csrfToken()
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Pure comparison (constant time) so it can be unit tested.
function csrfTokenMatches($expected, $submitted)
{
    return is_string($expected) && $expected !== '' && is_string($submitted) && hash_equals($expected, $submitted);
}

function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . escapeHtml(csrfToken()) . '">';
}

// Call at the start of every state-changing POST handler.
function verifyCsrfToken($post)
{
    startSecureSession();
    $expected = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    $submitted = (is_array($post) && isset($post['csrf_token'])) ? $post['csrf_token'] : '';
    if (!csrfTokenMatches($expected, $submitted)) {
        renderErrorPage(403, 'Request blocked', 'The security token was missing or invalid. Go back, reload the form and try again.');
    }
}

// Helper for pages that only accept POST for changes.
function requirePost()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('POST required');
    }
}
