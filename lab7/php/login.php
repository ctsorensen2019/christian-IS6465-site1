<?php
// UM-09 Log In (WF-08). Public page.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/userService.php';

startSecureSession();
if (!empty($_SESSION['user_id'])) {
    redirectTo('profile.php', 302);          // already signed in
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Fixed messages chosen by a whitelisted flag, so nothing from the URL is ever echoed.
$notices = array(
    'registered' => array('success', 'Account created. You can now log in.'),
    'logged_out' => array('info', 'You have been logged out.'),
    'expired' => array('warning', 'Your session has expired or had already ended. Please log in again.'),
    'inactive' => array('warning', 'Your account is not active. Contact an administrator.'),
    'deleted' => array('info', 'Your account has been deleted.'),
);
$notice = null;
foreach ($notices as $flag => $n) {
    if (isset($_GET[$flag])) {
        $notice = $n;
        break;
    }
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST);
    $email = inputString($_POST, 'email');
    try {
        $user = authenticateUser(openDatabase(), $email, inputString($_POST, 'password', false));
        if ($user) {
            establishSession($user);                  // regenerates the session ID
            redirectTo('profile.php');
        }
        http_response_code(401);
        $error = 'Invalid email or password.';        // same text for every failure reason
        $notice = null;
    } catch (Throwable $e) {
        error_log($e->getMessage());
        http_response_code(500);
        $error = 'Unable to log in right now. Please try again.';
        $notice = null;
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
    <h1>Log in</h1>
    <?php if ($notice): ?><div class="alert alert-<?= escapeHtml($notice[0]) ?> mt-3" role="status"><?= escapeHtml($notice[1]) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger mt-3" role="alert"><?= escapeHtml($error) ?></div><?php endif; ?>
    <form class="account-form" method="post" action="login.php">
        <?= csrfField() ?>
        <div class="form-field"><label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="254" required autocomplete="username" value="<?= escapeHtml($email) ?>"></div>
        <div class="form-field"><label for="password">Password</label>
            <input id="password" name="password" type="password" maxlength="72" required autocomplete="current-password"></div>
        <button type="submit">Log In</button>
    </form>
    <p class="text-center">New here? <a href="register.php">Create an account</a></p>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
