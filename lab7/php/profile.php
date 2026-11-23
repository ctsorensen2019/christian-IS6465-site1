<?php
// UM-02 View Own Account (WF-02, read-only view). Also the landing page after login.
// There is deliberately no user_id parameter: the page can only show the signed-in user.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/userService.php';

$pdo = openDatabase();
$user = requireLogin($pdo);                    // expired session -> login page
$profile = getOwnProfile($pdo, $user);

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-4">
    <h1>My profile</h1>
    <dl class="mt-3">
        <dt>Name</dt><dd><?= escapeHtml($profile['full_name']) ?></dd>
        <dt>Email</dt><dd><?= escapeHtml($profile['email']) ?></dd>
        <dt>Role</dt><dd><?= escapeHtml($profile['role']) ?> (read only)</dd>
        <dt>Status</dt><dd><?= escapeHtml($profile['status']) ?> (read only)</dd>
    </dl>
    <p>
        <a class="btn btn-primary" href="edit-profile.php">Edit Profile</a>
        <a class="btn btn-outline-secondary" href="change-password.php">Change Password</a>
    </p>
    <h2 class="h5 mt-4">What you can do</h2>
    <?php if ($profile['role'] === ROLE_STUDENT): ?>
        <p><a href="request-form.php">Request a tutoring session</a> or <a href="requests.php">view your requests</a>.</p>
    <?php elseif ($profile['role'] === ROLE_ADMIN): ?>
        <p><a href="users.php">Manage user accounts</a> or <a href="requests.php">review all tutoring requests</a>.</p>
    <?php else: ?>
        <p>Tutor accounts have no tutoring-request workflow in this version of the application.</p>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
