<?php
// UM-01 Create User Account (WF-01). Public page: Student or Tutor self-registration.
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

$errors = array();
$old = array('full_name' => '', 'email' => '', 'role' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST);
    // Passwords are never echoed back into the form.
    $old = array(
        'full_name' => inputString($_POST, 'full_name'),
        'email' => inputString($_POST, 'email'),
        'role' => inputString($_POST, 'role'),
    );
    try {
        registerUser(openDatabase(), $_POST);
        redirectTo('login.php?registered=1');        // confirmation is shown on the login page
    } catch (PermissionDeniedException $e) {
        http_response_code(403);
        $errors = array($e->getMessage());
    } catch (ValidationException $e) {
        http_response_code(422);
        $errors = $e->getErrors();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        http_response_code(500);
        $errors = array('Unable to create the account. Please try again.');
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
    <h1>Create an account</h1>
    <?php if ($errors): ?>
        <div class="alert alert-danger mt-3" role="alert"><ul class="mb-0">
            <?php foreach ($errors as $error): ?><li><?= escapeHtml($error) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <form class="account-form" method="post" action="register.php" novalidate>
        <?= csrfField() ?>
        <div class="form-field"><label for="full_name">Full name</label>
            <input id="full_name" name="full_name" maxlength="120" required autocomplete="name" value="<?= escapeHtml($old['full_name']) ?>"></div>
        <div class="form-field"><label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="254" required autocomplete="email" value="<?= escapeHtml($old['email']) ?>"></div>
        <div class="form-field"><label for="password">Password</label>
            <input id="password" name="password" type="password" maxlength="72" required autocomplete="new-password">
            <small class="text-muted">8 to 72 characters, with at least one letter and one number.</small></div>
        <div class="form-field"><label for="confirm_password">Confirm password</label>
            <input id="confirm_password" name="confirm_password" type="password" maxlength="72" required autocomplete="new-password"></div>
        <fieldset>
            <legend>Account type</legend>
            <?php foreach (selfRegistrationRoles() as $r): ?>
                <label class="choice"><input type="radio" name="role" value="<?= escapeHtml($r) ?>" <?= $old['role'] === $r ? 'checked' : '' ?> required> <span><?= escapeHtml($r) ?></span></label>
            <?php endforeach; ?>
        </fieldset>
        <button type="submit">Create Account</button>
    </form>
    <p class="text-center">Already registered? <a href="login.php">Log in</a></p>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
