<?php
// UM-03 step 4: permitted details for one account, with links to the UM-06 / UM-07 / UM-08
// screens. Administrator only. The password hash is never selected for this page.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/userService.php';

$pdo = openDatabase();
$user = requireLogin($pdo);
requireRole($user, array(ROLE_ADMIN));
$targetId = requirePositiveInt($_GET, 'user_id');

try {
    $target = getUserForAdministrator($pdo, $user, $targetId);
} catch (NotFoundException $e) {
    renderErrorPage(404, 'Not found', 'That account does not exist.', 'users.php', 'Back to Manage Users');
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
    <h1>Account: <?= escapeHtml($target['full_name']) ?></h1>
    <dl class="mt-3">
        <dt>User ID</dt><dd><?= (int)$target['user_id'] ?></dd>
        <dt>Email</dt><dd><?= escapeHtml($target['email']) ?></dd>
        <dt>Role</dt><dd><?= escapeHtml($target['role']) ?></dd>
        <dt>Status</dt><dd><?= escapeHtml($target['status']) ?></dd>
        <dt>Created</dt><dd><?= escapeHtml($target['created_at']) ?></dd>
        <dt>Tutoring requests</dt><dd><?= (int)$target['request_count'] ?></dd>
    </dl>
    <p>
        <a class="btn btn-primary" href="user-role.php?user_id=<?= (int)$target['user_id'] ?>">Change Role</a>
        <a class="btn btn-warning" href="user-status.php?user_id=<?= (int)$target['user_id'] ?>"><?= $target['status'] === STATUS_ACTIVE ? 'Deactivate' : 'Activate' ?></a>
        <a class="btn btn-outline-danger" href="user-delete.php?user_id=<?= (int)$target['user_id'] ?>">Delete Account</a>
        <a class="btn btn-secondary" href="users.php">Back to list</a>
    </p>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
