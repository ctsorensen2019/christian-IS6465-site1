<?php
// UC01 submit. Order of checks (handout section 15):
//   login -> role -> CSRF token -> input validation -> transaction -> redirect.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/requestService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required');
}
$pdo = openDatabase();
$user = requireLogin($pdo);
requireRole($user, array('Student'));
verifyCsrfToken($_POST);
if (inputString($_POST, 'form_name') !== 'tutoring_request') {
    http_response_code(400);
    exit('Invalid form');
}
try {
    $id = submitRequest($pdo, $_POST, $user);
    header('Location: request-details.php?request_id=' . $id, true, 303);
    exit;
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo '<h1>Request could not be submitted</h1><p>' . escapeHtml($e->getMessage()) . '</p><a href="request-form.php">Return to form</a>';
} catch (PermissionDeniedException $e) {
    renderErrorPage(403, 'Access denied', $e->getMessage());
} catch (ConflictException $e) {
    renderErrorPage(409, 'Request could not be submitted', $e->getMessage(), 'request-form.php', 'Return to form');
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to save request');
}
