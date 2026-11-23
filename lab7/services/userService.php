<?php
// Service layer for user management (UM-01 .. UM-10): validation, authorization rules
// and transactions. No SQL here (see repositories/) and no HTML (see php/ pages).
//
// Convention: functions that act on behalf of someone take an $actor argument. The actor
// is the row returned by requireLogin(), i.e. freshly loaded from SQLite, never data
// supplied by the browser. Services re-check the actor's role even though pages already
// did, so a page that forgets a check cannot bypass the rule.

require_once dirname(__DIR__) . '/includes/errors.php';
require_once dirname(__DIR__) . '/includes/validation.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/repositories/userPDO.php';
require_once dirname(__DIR__) . '/repositories/studentPDO.php';

// A valid bcrypt hash of a throwaway string. authenticateUser() verifies against it when
// the email is unknown so that "no such account" takes about as long as "wrong password".
const DUMMY_PASSWORD_HASH = '$2y$10$0q4TwENaWGbJ7v0imDN91.gbWv.6dtpELz3tdzkia0CXVREecbPQ2';

function normalizeEmail($email)
{
    return strtolower(trim($email));
}

function isUniqueViolation(PDOException $e)
{
    return (string)$e->getCode() === '23000';
}

function assertAdministrator($actor)
{
    if (!is_array($actor) || !isset($actor['role']) || $actor['role'] !== ROLE_ADMIN) {
        throw new PermissionDeniedException('Administrator access is required.');
    }
}

// ------------------------------------------------------------------- UM-01 ----

// Public self-registration. Creates an Active Student or Tutor. Returns the new user_id.
// Throws PermissionDeniedException (Administrator requested) or ValidationException.
function registerUser($pdo, $input)
{
    $role = inputString($input, 'role');
    if ($role === ROLE_ADMIN) {
        // Approved rule: public registration can never grant Administrator privileges.
        throw new PermissionDeniedException('Administrator accounts cannot be created through public registration.');
    }

    $name = inputString($input, 'full_name');
    $email = normalizeEmail(inputString($input, 'email'));
    $password = inputString($input, 'password', false);
    $confirm = inputString($input, 'confirm_password', false);

    $errors = array();
    foreach (array(
        validateFullName($name),
        validateEmail($email),
        validatePassword($password),
        validatePasswordConfirmation($password, $confirm),
        validateRole($role, selfRegistrationRoles()),
    ) as $message) {
        if ($message !== '') {
            $errors[] = $message;
        }
    }
    if ($errors) {
        throw new ValidationException($errors);
    }
    if (findUserByEmail($pdo, $email)) {
        throw new ValidationException(array('An account with that email address already exists.'));
    }

    try {
        return insertUser($pdo, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role, STATUS_ACTIVE);
    } catch (PDOException $e) {
        if (isUniqueViolation($e)) {   // two registrations raced past the check above
            throw new ValidationException(array('An account with that email address already exists.'));
        }
        throw $e;
    }
}

// ------------------------------------------------------------------- UM-02 ----

// Own profile only: the caller passes the signed-in user's ID, there is no way to ask
// for someone else's row through this function.
function getOwnProfile($pdo, $actor)
{
    $user = findUserById($pdo, (int)$actor['user_id']);
    if (!$user) {
        throw new NotFoundException('Account not found.');
    }
    return $user;
}

// ------------------------------------------------------------------- UM-03 ----

// Administrator search. $filters keys: q, role, status ('' = All).
function searchUserAccounts($pdo, $actor, $filters)
{
    assertAdministrator($actor);
    $text = inputString($filters, 'q');
    $role = inputString($filters, 'role');
    $status = inputString($filters, 'status');
    $errors = array();
    if (mb_strlen($text) > 100) {
        $errors[] = 'Search text is too long.';
    }
    if ($role !== '' && validateRole($role, allRoles()) !== '') {
        $errors[] = 'Invalid role filter.';
    }
    if ($status !== '' && validateStatus($status) !== '') {
        $errors[] = 'Invalid status filter.';
    }
    if ($errors) {
        throw new ValidationException($errors);
    }
    return searchUsers($pdo, $text, $role, $status);
}

// Permitted account details for one account (never includes the password hash).
function getUserForAdministrator($pdo, $actor, $userId)
{
    assertAdministrator($actor);
    $user = findUserById($pdo, (int)$userId);
    if (!$user) {
        throw new NotFoundException('Account not found.');
    }
    $user['request_count'] = countRequestsForUser($pdo, (int)$userId);
    return $user;
}

// ------------------------------------------------------------------- UM-04 ----

// Edit own name and email. Throws PermissionDeniedException if the submission tries to
// include role or status (they can never change through ordinary profile edits).
function updateOwnProfile($pdo, $actor, $input)
{
    if (array_key_exists('role', $input) || array_key_exists('status', $input)) {
        throw new PermissionDeniedException('Role and status cannot be changed from your profile.');
    }
    $userId = (int)$actor['user_id'];            // ownership: always the signed-in user
    $name = inputString($input, 'full_name');
    $email = normalizeEmail(inputString($input, 'email'));

    $errors = array();
    foreach (array(validateFullName($name), validateEmail($email)) as $message) {
        if ($message !== '') {
            $errors[] = $message;
        }
    }
    if ($errors) {
        throw new ValidationException($errors);
    }
    if (emailInUseByOther($pdo, $email, $userId)) {
        throw new ValidationException(array('That email address is already in use.'));
    }

    $pdo->beginTransaction();
    try {
        updateUserProfile($pdo, $userId, $name, $email);
        syncStudentProfile($pdo, $userId, $name, $email);   // keep request lists consistent
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (isUniqueViolation($e)) {
            throw new ValidationException(array('That email address is already in use.'));
        }
        throw $e;
    }
    return true;
}

// ------------------------------------------------------------------- UM-05 ----

function changeOwnPassword($pdo, $actor, $current, $new, $confirm)
{
    $userId = (int)$actor['user_id'];
    $row = findUserAuthById($pdo, $userId);
    if (!$row) {
        throw new NotFoundException('Account not found.');
    }
    $errors = array();
    if (!is_string($current) || !password_verify($current, $row['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    }
    foreach (array(validatePassword($new), validatePasswordConfirmation($new, $confirm)) as $message) {
        if ($message !== '') {
            $errors[] = $message;
        }
    }
    if ($errors) {
        throw new ValidationException($errors);
    }
    updateUserPassword($pdo, $userId, password_hash($new, PASSWORD_DEFAULT));
    return true;
}

// ------------------------------------------------------------------- UM-06 ----

function changeUserRole($pdo, $actor, $targetUserId, $newRole)
{
    assertAdministrator($actor);
    $message = validateRole($newRole, allRoles());
    if ($message !== '') {
        throw new ValidationException(array($message));
    }
    $target = findUserById($pdo, (int)$targetUserId);
    if (!$target) {
        throw new NotFoundException('Account not found.');
    }
    if ($target['role'] === $newRole) {
        throw new ValidationException(array('The account already has that role.'));
    }
    if (!updateUserRoleGuarded($pdo, (int)$targetUserId, $newRole)) {
        throw new ConflictException('The last active administrator cannot be changed to another role.');
    }
    return true;
}

// ------------------------------------------------------------------- UM-07 ----

function setUserStatus($pdo, $actor, $targetUserId, $newStatus)
{
    assertAdministrator($actor);
    $message = validateStatus($newStatus);
    if ($message !== '') {
        throw new ValidationException(array($message));
    }
    $target = findUserById($pdo, (int)$targetUserId);
    if (!$target) {
        throw new NotFoundException('Account not found.');
    }
    if ($target['status'] === $newStatus) {
        // Active -> Active and Inactive -> Inactive are not valid transitions.
        throw new ValidationException(array('The account is already ' . $newStatus . '.'));
    }
    if (!updateUserStatusGuarded($pdo, (int)$targetUserId, $newStatus)) {
        throw new ConflictException('The last active administrator cannot be deactivated.');
    }
    return true;
}

// ------------------------------------------------------------------- UM-08 ----

// WF-07 warning screen: what would deleting this account affect, and is it allowed?
// OPEN DECISION in the design ("permanent deletion policy, related tutoring request
// retention"). Conservative default implemented here, pending approval:
//   * an account with ANY related tutoring request (any status) cannot be deleted;
//     requests are retained and never removed silently;
//   * the last active administrator cannot be deleted.
function previewAccountDeletion($pdo, $actor, $targetUserId)
{
    assertAdministrator($actor);
    $target = findUserById($pdo, (int)$targetUserId);
    if (!$target) {
        throw new NotFoundException('Account not found.');
    }
    $count = countRequestsForUser($pdo, (int)$targetUserId);
    $reason = '';
    if ($count > 0) {
        $reason = 'This account has ' . $count . ' related tutoring request(s). Requests are retained under the current policy, so the account cannot be deleted. Deactivate it instead.';
    }
    return array('user' => $target, 'request_count' => $count, 'blocked_reason' => $reason);
}

// Authorization and relationships are checked again here (step 2 of the use case), inside
// a transaction, so the answer shown on the warning screen cannot be stale.
function deleteUserAccount($pdo, $actor, $targetUserId)
{
    assertAdministrator($actor);
    $targetUserId = (int)$targetUserId;
    $pdo->beginTransaction();
    try {
        $preview = previewAccountDeletion($pdo, $actor, $targetUserId);
        if ($preview['blocked_reason'] !== '') {
            throw new ConflictException($preview['blocked_reason']);
        }
        deleteEmptyStudentForUser($pdo, $targetUserId);          // holds no tutoring data
        if (!deleteUserGuarded($pdo, $targetUserId)) {
            throw new ConflictException('The last active administrator cannot be deleted.');
        }
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Foreign-key refusal: related rows appeared after the preview. Nothing was deleted.
        throw new ConflictException('The account is referenced by other records and cannot be deleted.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// ------------------------------------------------------------------- UM-09 ----

// Verify credentials and account status. Returns the user row (without the hash) or
// null. Unknown email, Inactive account and wrong password are indistinguishable to the
// caller and to the person at the keyboard (handout section 7).
function authenticateUser($pdo, $email, $password)
{
    $email = normalizeEmail((string)$email);
    $password = (string)$password;
    $row = ($email === '') ? false : findUserByEmail($pdo, $email);

    $hash = $row ? $row['password_hash'] : DUMMY_PASSWORD_HASH;
    $passwordOk = password_verify($password, $hash);
    if (!$row || !$passwordOk || $row['status'] !== STATUS_ACTIVE) {
        return null;
    }
    // Upgrade the stored hash if PHP's recommended algorithm or cost has moved on.
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        updateUserPassword($pdo, (int)$row['user_id'], password_hash($password, PASSWORD_DEFAULT));
    }
    unset($row['password_hash']);
    return $row;
}
