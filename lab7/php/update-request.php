<?php
// Save an edit to an Open request. login -> role -> CSRF -> validation -> ownership-checked UPDATE.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/requestService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required');
}
$pdo = openDatabase();
$user = requireLogin($pdo);
requireRole($user, array('Student', 'Administrator'));
verifyCsrfToken($_POST);
$id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid ID');
}
// 404 first when the actor may not see this request (same answer as "does not exist").
if (!getRequestForActor($pdo, $user, $id)) {
    http_response_code(404);
    exit('Not found');
}
try {
    $ok = reviseRequest($pdo, $id, $_POST, $user);
    if (!$ok) {
        http_response_code(409);
        exit('Request not found or not Open');
    }
    header('Location: request-details.php?request_id=' . $id, true, 303);
    exit;
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    exit(escapeHtml($e->getMessage()));
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to update request');
}
