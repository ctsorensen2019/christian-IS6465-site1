<?php
// UM-08 Delete User Account (WF-07). Administrator only.
// GET: warning with related-record implications (step 1).
// POST: re-check authorization and relationships, delete only if policy allows (steps 2-3).
// Policy (OPEN DECISION, conservative default): an account that has any tutoring request
// cannot be deleted; requests are never removed silently.
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
    if ($isPost) {
        verifyCsrfToken($_POST);
        try {
            deleteUserAccount($pdo, $user, $targetId);
            if ($targetId === (int)$user['user_id']) {   // an administrator deleted their own account
                logoutSession();
                redirectTo('login.php?deleted=1');
            }
            setFlash('success', 'Account deleted.');
            redirectTo('users.php');
        } catch (ConflictException $e) {
            http_response_code(409);
            $errors = array($e->getMessage());
        }
    }
    $preview = previewAccountDeletion($pdo, $user, $targetId);
} catch (NotFoundException $e) {
    renderErrorPage(404, 'Not found', 'That account does not exist.', 'users.php', 'Back to Manage Users');
} catch (PermissionDeniedException $e) {
    renderErrorPage(403, 'Access denied', $e->getMessage());
}
$target = $preview['user'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
    <h1>Delete account</h1>
    <?php if ($errors): ?>
        <div class="alert alert-danger mt-3" role="alert"><ul class="mb-0">
            <?php foreach ($errors as $error): ?><li><?= escapeHtml($error) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <form class="account-form" method="post" action="user-delete.php">
        <?= csrfField() ?>
        <input type="hidden" name="user_id" value="<?= (int)$target['user_id'] ?>">
        <p><strong>Delete account for <?= escapeHtml($target['full_name']) ?>?</strong></p>
        <p>Related tutoring requests: <?= (int)$preview['request_count'] ?>. Deletion is permanent.</p>
        <?php if ($preview['blocked_reason'] !== ''): ?>
            <div class="alert alert-warning" role="alert"><?= escapeHtml($preview['blocked_reason']) ?></div>
        <?php else: ?>
            <button type="submit">Confirm Delete</button>
        <?php endif; ?>
    </form>
    <p class="text-center"><a href="user-details.php?user_id=<?= (int)$target['user_id'] ?>">Cancel</a></p>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
