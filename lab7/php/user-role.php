<?php
// UM-06 Update User Role (WF-05). Administrator only. GET shows the form, POST saves.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/userService.php';

$pdo = openDatabase();
$user = requireLogin($pdo);
requireRole($user, array(ROLE_ADMIN));
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$targetId = requirePositiveInt($isPost ? $_POST : $_GET, 'user_id');

$errors = array();
try {
    $target = getUserForAdministrator($pdo, $user, $targetId);
    if ($isPost) {
        verifyCsrfToken($_POST);
        try {
            changeUserRole($pdo, $user, $targetId, inputString($_POST, 'new_role'));
            setFlash('success', 'Role updated for ' . $target['full_name'] . '.');
            redirectTo('user-details.php?user_id=' . $targetId);
        } catch (ValidationException $e) {
            http_response_code(422);
            $errors = $e->getErrors();
        } catch (ConflictException $e) {
            http_response_code(409);
            $errors = array($e->getMessage());
        }
    }
} catch (NotFoundException $e) {
    renderErrorPage(404, 'Not found', 'That account does not exist.', 'users.php', 'Back to Manage Users');
} catch (PermissionDeniedException $e) {
    renderErrorPage(403, 'Access denied', $e->getMessage());
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
    <h1>Update role</h1>
    <?php if ($errors): ?>
        <div class="alert alert-danger mt-3" role="alert"><ul class="mb-0">
            <?php foreach ($errors as $error): ?><li><?= escapeHtml($error) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <form class="account-form" method="post" action="user-role.php">
        <?= csrfField() ?>
        <input type="hidden" name="user_id" value="<?= (int)$target['user_id'] ?>">
        <p><strong>User:</strong> <?= escapeHtml($target['full_name']) ?><br>
           <strong>Current role:</strong> <?= escapeHtml($target['role']) ?></p>
        <div class="form-field"><label for="new_role">New role</label>
            <select id="new_role" name="new_role" required>
                <?php foreach (allRoles() as $r): ?><option value="<?= escapeHtml($r) ?>" <?= $r === $target['role'] ? 'selected' : '' ?>><?= escapeHtml($r) ?></option><?php endforeach; ?>
            </select></div>
        <button type="submit">Save Role</button>
    </form>
    <p class="text-center"><a href="user-details.php?user_id=<?= (int)$target['user_id'] ?>">Cancel</a></p>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
