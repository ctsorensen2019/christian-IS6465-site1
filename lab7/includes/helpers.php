<?php
// Small presentation helpers shared by every page.

// Encode untrusted text before it is placed in HTML (output encoding).
function escapeHtml($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Redirect and stop. Relative URLs only, so the app also works under a path prefix.
// 303 turns the browser's next request into a GET (Post/Redirect/Get).
function redirectTo($url, $code = 303)
{
    header('Location: ' . $url, true, $code);
    exit;
}

// One-time message shown on the next page (stored in the server-side session).
function setFlash($type, $text)
{
    $_SESSION['flash'] = array('type' => $type, 'text' => $text);
}

function takeFlash()
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

// Show a complete error page (403, 404, 409 ...) and stop.
function renderErrorPage($status, $title, $message, $backHref = 'profile.php', $backLabel = 'Back')
{
    http_response_code($status);
    require __DIR__ . '/header.php';
    require __DIR__ . '/nav.php';
    echo '<main class="container py-4"><h1>' . escapeHtml($title) . '</h1>'
        . '<p class="mt-3">' . escapeHtml($message) . '</p>'
        . '<a class="btn btn-secondary" href="' . escapeHtml($backHref) . '">' . escapeHtml($backLabel) . '</a></main>';
    require __DIR__ . '/footer.php';
    exit;
}

// Read a text field from submitted input. Arrays (for example role[]=x) and missing
// keys become '' so that tampered input can never reach string functions as an array.
function inputString($input, $key, $trim = true)
{
    if (!isset($input[$key]) || !is_string($input[$key])) {
        return '';
    }
    return $trim ? trim($input[$key]) : $input[$key];
}

// Positive integer from $_GET / $_POST (for example user_id), or a 400 page.
function requirePositiveInt($source, $key)
{
    $value = isset($source[$key]) && is_string($source[$key])
        ? filter_var($source[$key], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))
        : false;
    if ($value === false) {
        renderErrorPage(400, 'Invalid request', 'A valid ' . $key . ' is required.', 'users.php', 'Back to Manage Users');
    }
    return (int)$value;
}
