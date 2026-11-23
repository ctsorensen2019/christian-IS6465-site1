<?php
// Cancel an Open request (status change; the record is retained). Student: own requests;
// Administrator: any Open request. GET shows the confirmation, POST (with CSRF token) cancels.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/requestService.php';

$pdo = openDatabase();
$user = requireLogin($pdo);
requireRole($user, array('Student', 'Administrator'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST);
    $id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        http_response_code(400);
        exit('Invalid ID');
    }
    if (!getRequestForActor($pdo, $user, $id)) {
        http_response_code(404);
        exit('Not found');
    }
    try {
        $ok = cancelRequest($pdo, $id, $user);
        if (!$ok) {
            http_response_code(409);
            exit('Request not found or not Open');
        }
        header('Location: request-details.php?request_id=' . $id, true, 303);
        exit;
    } catch (Throwable $e) {
        error_log($e->getMessage());
        http_response_code(500);
        exit('Unable to cancel');
    }
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}
$id = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid ID');
}
$r = getRequestForActor($pdo, $user, $id);
if (!$r) {
    http_response_code(404);
    exit('Not found');
}
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1>Cancel tutoring request</h1>
    <p>Cancel request #<?= $id ?>? The record will be retained with status Cancelled.</p>
    <?php if ($r['status'] === 'Open'): ?>
        <form method="post"><?= csrfField() ?><input type="hidden" name="request_id" value="<?= $id ?>"><button class="btn btn-danger">Yes, cancel request</button></form>
    <?php else: ?>
        <p>Only Open requests can be cancelled.</p>
    <?php endif; ?>
    <a href="request-details.php?request_id=<?= $id ?>">Keep request</a>
</main><?php require __DIR__ . '/../includes/footer.php'; ?>
