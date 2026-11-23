<?php
// UM-04 Update Own Account (WF-02, edit mode). Name and email only; role and status are
// shown read-only and are never read from the submitted form.
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
$form = array('full_name' => $user['full_name'], 'email' => $user['email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST);
    $form = array('full_name' => inputString($_POST, 'full_name'), 'email' => inputString($_POST, 'email'));
    try {
        updateOwnProfile($pdo, $user, $_POST);
        setFlash('success', 'Your profile was updated.');
        redirectTo('profile.php');
    } catch (PermissionDeniedException $e) {
        renderErrorPage(403, 'Access denied', $e->getMessage(), 'profile.php', 'Back to profile');
    } catch (ValidationException $e) {
        http_response_code(422);
        $errors = $e->getErrors();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        http_response_code(500);
        $errors = array('Unable to save your changes. Please try again.');
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
    <h1>Edit profile</h1>
    <?php if ($errors): ?>
        <div class="alert alert-danger mt-3" role="alert"><ul class="mb-0">
            <?php foreach ($errors as $error): ?><li><?= escapeHtml($error) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <form class="account-form" method="post" action="edit-profile.php" novalidate>
        <?= csrfField() ?>
        <div class="form-field"><label for="full_name">Name</label>
            <input id="full_name" name="full_name" maxlength="120" required value="<?= escapeHtml($form['full_name']) ?>"></div>
        <div class="form-field"><label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="254" required value="<?= escapeHtml($form['email']) ?>"></div>
        <p><strong>Role:</strong> <?= escapeHtml($user['role']) ?> (read only)<br>
           <strong>Status:</strong> <?= escapeHtml($user['status']) ?> (read only)</p>
        <button type="submit">Save Changes</button>
    </form>
    <p class="text-center"><a href="profile.php">Cancel</a></p>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
