<?php
// UM-07 Activate or Deactivate Account (WF-06). Administrator only.
// GET shows the confirmation screen (step 2), POST performs the change (step 3).
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
    // The only valid transition is to the opposite status.
    $newStatus = $target['status'] === STATUS_ACTIVE ? STATUS_INACTIVE : STATUS_ACTIVE;
    if ($isPost) {
        verifyCsrfToken($_POST);
        try {
            // The submitted value is validated against the current state by the service,
            // so a stale or forged form cannot produce an invalid transition.
            setUserStatus($pdo, $user, $targetId, inputString($_POST, 'new_status'));
            setFlash('success', $target['full_name'] . ' is now ' . inputString($_POST, 'new_status') . '.');
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
    <h1><?= $newStatus === STATUS_INACTIVE ? 'Deactivate' : 'Activate' ?> account</h1>
    <?php if ($errors): ?>
        <div class="alert alert-danger mt-3" role="alert"><ul class="mb-0">
            <?php foreach ($errors as $error): ?><li><?= escapeHtml($error) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <form class="account-form" method="post" action="user-status.php">
        <?= csrfField() ?>
        <input type="hidden" name="user_id" value="<?= (int)$target['user_id'] ?>">
        <input type="hidden" name="new_status" value="<?= escapeHtml($newStatus) ?>">
        <p><strong>User:</strong> <?= escapeHtml($target['full_name']) ?><br>
           <strong>Current status:</strong> <?= escapeHtml($target['status']) ?><br>
           <strong>New status:</strong> <?= escapeHtml($newStatus) ?></p>
        <?php if ($newStatus === STATUS_INACTIVE): ?><p>An inactive user cannot log in, and any session they have open ends on their next request.</p><?php endif; ?>
        <button type="submit">Confirm</button>
    </form>
    <p class="text-center"><a href="user-details.php?user_id=<?= (int)$target['user_id'] ?>">Cancel</a></p>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
