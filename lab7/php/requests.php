<?php
// UC02 list and filter. Students see only their own requests; administrators see all.
// Tutors have no request workflow (403).
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/requestService.php';

$pdo = openDatabase();
$user = requireLogin($pdo);
requireRole($user, array('Student', 'Administrator'));

$course = trim((string)(isset($_GET['course']) && is_string($_GET['course']) ? $_GET['course'] : ''));
$status = (string)(isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : '');
if ($course !== '' && !in_array($course, array('IS 6465', 'IS 6495', 'IS 6570'), true)) {
    http_response_code(400);
    exit('Invalid course');
}
if ($status !== '' && !in_array($status, array('Open', 'Cancelled', 'Assigned', 'Completed'), true)) {
    http_response_code(400);
    exit('Invalid status');
}
$rows = listRequestsForActor($pdo, $user, $course, $status);
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php'; ?>
<main class="container py-4">
    <h1><?= $user['role'] === 'Administrator' ? 'Tutoring requests' : 'My tutoring requests' ?></h1>
    <form method="get" class="row g-2 mb-3">
        <div class="col"><select class="form-select" name="course">
                <option value="">All courses</option><?php foreach (array('IS 6465', 'IS 6495', 'IS 6570') as $c): ?><option value="<?= escapeHtml($c) ?>" <?= $c === $course ? 'selected' : '' ?>><?= escapeHtml($c) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col"><select class="form-select" name="status">
                <option value="">All statuses</option><?php foreach (array('Open', 'Cancelled', 'Assigned', 'Completed') as $st): ?><option value="<?= escapeHtml($st) ?>" <?= $st === $status ? 'selected' : '' ?>><?= escapeHtml($st) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
    </form>
    <?php if (!$rows): ?><p>No matching requests.</p><?php else: ?><table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Details</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody><?php foreach ($rows as $r): $topic = decodeTopic($r['topic']); ?><tr>
                        <td><?= (int)$r['request_id'] ?></td>
                        <td><?= escapeHtml($r['student_name']) ?></td>
                        <td><?= escapeHtml($r['course']) ?></td>
                        <td><?= escapeHtml($topic['reason']) ?></td>
                        <td><?= escapeHtml($r['preferred_date']) ?></td>
                        <td><?= escapeHtml($r['status']) ?></td>
                        <td><a href="request-details.php?request_id=<?= (int)$r['request_id'] ?>">View</a></td>
                    </tr><?php endforeach; ?></tbody>
        </table><?php endif; ?>
</main><?php require __DIR__ . '/../includes/footer.php'; ?>
