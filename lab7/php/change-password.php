<?php
// UM-05 Change Password (WF-04). Verifies the current password, stores only a new hash.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/userService.php';

$pdo = openDatabase();
$user = requireLogin($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST);
    try {
        changeOwnPassword(
            $pdo,
            $user,
            inputString($_POST, 'current_password', false),
            inputString($_POST, 'new_password', false),
            inputString($_POST, 'confirm_password', false)
        );
        session_regenerate_id(true);               // credentials changed: issue a new session ID
        setFlash('success', 'Your password was updated.');
        redirectTo('profile.php');
    } catch (ValidationException $e) {
        http_response_code(422);
        $errors = $e->getErrors();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        http_response_code(500);
        $errors = array('Unable to change the password. Please try again.');
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
    <h1>Change password</h1>
    <?php if ($errors): ?>
        <div class="alert alert-danger mt-3" role="alert"><ul class="mb-0">
            <?php foreach ($errors as $error): ?><li><?= escapeHtml($error) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <form class="account-form" method="post" action="change-password.php" novalidate>
        <?= csrfField() ?>
        <div class="form-field"><label for="current_password">Current password</label>
            <input id="current_password" name="current_password" type="password" maxlength="72" required autocomplete="current-password"></div>
        <div class="form-field"><label for="new_password">New password</label>
            <input id="new_password" name="new_password" type="password" maxlength="72" required autocomplete="new-password">
            <small class="text-muted">8 to 72 characters, with at least one letter and one number.</small></div>
        <div class="form-field"><label for="confirm_password">Confirm new password</label>
            <input id="confirm_password" name="confirm_password" type="password" maxlength="72" required autocomplete="new-password"></div>
        <button type="submit">Update Password</button>
    </form>
    <p class="text-center"><a href="profile.php">Cancel</a></p>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
