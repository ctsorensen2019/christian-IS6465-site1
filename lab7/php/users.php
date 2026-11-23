<?php
// UM-03 View and Search User Accounts (WF-03 Manage Users). Administrator only.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/userService.php';

$pdo = openDatabase();
$user = requireLogin($pdo);
requireRole($user, array(ROLE_ADMIN));         // students and tutors get 403 here

$q = inputString($_GET, 'q');
$role = inputString($_GET, 'role');
$status = inputString($_GET, 'status');
try {
    $rows = searchUserAccounts($pdo, $user, array('q' => $q, 'role' => $role, 'status' => $status));
} catch (ValidationException $e) {
    renderErrorPage(400, 'Invalid search', $e->getMessage(), 'users.php', 'Back to Manage Users');
} catch (PermissionDeniedException $e) {
    renderErrorPage(403, 'Access denied', $e->getMessage());     // second line of defence behind requireRole()
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
    <h1>Manage users</h1>
    <form method="get" action="users.php" class="row g-2 mb-3">
        <div class="col"><input class="form-control" type="search" name="q" maxlength="100" placeholder="Search name or email" value="<?= escapeHtml($q) ?>"></div>
        <div class="col"><select class="form-select" name="role" aria-label="Role">
            <option value="">All roles</option>
            <?php foreach (allRoles() as $r): ?><option value="<?= escapeHtml($r) ?>" <?= $r === $role ? 'selected' : '' ?>><?= escapeHtml($r) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col"><select class="form-select" name="status" aria-label="Status">
            <option value="">All statuses</option>
            <?php foreach (allStatuses() as $s): ?><option value="<?= escapeHtml($s) ?>" <?= $s === $status ? 'selected' : '' ?>><?= escapeHtml($s) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-auto"><button class="btn btn-primary">Search</button></div>
    </form>
    <?php if (!$rows): ?>
        <p>No matching users.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Manage</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= escapeHtml($r['full_name']) ?></td>
                    <td><?= escapeHtml($r['email']) ?></td>
                    <td><?= escapeHtml($r['role']) ?></td>
                    <td><?= escapeHtml($r['status']) ?></td>
                    <td><a href="user-details.php?user_id=<?= (int)$r['user_id'] ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
