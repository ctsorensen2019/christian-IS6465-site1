<?php
// Role-aware navigation. Hiding a link is a convenience only: every destination page
// still enforces login, role and ownership on the server.
require_once __DIR__ . '/auth.php';
$navSignedIn = session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id']);
$navRole = $navSignedIn && isset($_SESSION['role']) ? $_SESSION['role'] : '';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand" href="../index.php">Peer Tutoring Hub</a>
    <div class="navbar-nav align-items-lg-center">
<?php if ($navSignedIn): ?>
<?php if ($navRole === 'Student'): ?>
      <a class="nav-link" href="request-form.php">Request a Session</a>
      <a class="nav-link" href="requests.php">My Requests</a>
<?php elseif ($navRole === 'Administrator'): ?>
      <a class="nav-link" href="requests.php">Requests</a>
      <a class="nav-link" href="users.php">Manage Users</a>
<?php endif; ?>
      <a class="nav-link" href="profile.php">My Profile (<?= escapeHtml(isset($_SESSION['display_name']) ? $_SESSION['display_name'] : '') ?>)</a>
      <form class="d-inline" method="post" action="logout.php">
        <?= csrfField() ?>
        <button class="btn btn-link nav-link" type="submit">Log Out</button>
      </form>
<?php else: ?>
      <a class="nav-link" href="login.php">Log In</a>
      <a class="nav-link" href="register.php">Register</a>
<?php endif; ?>
    </div>
  </div>
</nav>
<?php $navFlash = takeFlash(); if ($navFlash): ?>
<div class="container mt-3"><div class="alert alert-<?= escapeHtml($navFlash['type']) ?>" role="status"><?= escapeHtml($navFlash['text']) ?></div></div>
<?php endif; ?>
