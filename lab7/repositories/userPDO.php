<?php
// Repository for the users table. SQL lives here and nowhere else.
// Every value reaches SQLite through a prepared statement. password_hash is only
// selected by the two functions that need it (findUserByEmail, findUserAuthById), so
// ordinary reads can never leak it to a page.

const USER_PUBLIC_COLUMNS = 'user_id, full_name, email, role, status, created_at';

function findUserById($pdo, $userId)
{
    $stmt = $pdo->prepare('SELECT ' . USER_PUBLIC_COLUMNS . ' FROM users WHERE user_id = ?');
    $stmt->execute(array($userId));
    return $stmt->fetch();
}

// UM-09: candidate account for a login attempt (includes the hash for password_verify).
function findUserByEmail($pdo, $email)
{
    $stmt = $pdo->prepare('SELECT user_id, full_name, email, password_hash, role, status FROM users WHERE email = ?');
    $stmt->execute(array($email));
    return $stmt->fetch();
}

// UM-05: current hash for the signed-in user.
function findUserAuthById($pdo, $userId)
{
    $stmt = $pdo->prepare('SELECT user_id, password_hash FROM users WHERE user_id = ?');
    $stmt->execute(array($userId));
    return $stmt->fetch();
}

function emailInUseByOther($pdo, $email, $exceptUserId)
{
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND user_id <> ?');
    $stmt->execute(array($email, $exceptUserId));
    return $stmt->fetchColumn() !== false;
}

// UM-01 (and the demo seeder). $passwordHash must already be a password_hash() value.
function insertUser($pdo, $fullName, $email, $passwordHash, $role, $status = 'Active')
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute(array($fullName, $email, $passwordHash, $role, $status));
    return (int)$pdo->lastInsertId();
}

// UM-03: optional text search plus role and status filters. '' means "All".
function searchUsers($pdo, $text, $role, $status)
{
    $sql = 'SELECT ' . USER_PUBLIC_COLUMNS . ' FROM users WHERE 1 = 1';
    $args = array();
    if ($text !== '') {
        // Escape LIKE wildcards so a search for "%" or "_" matches those characters literally.
        $like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $text) . '%';
        $sql .= " AND (full_name LIKE ? ESCAPE '\\' OR email LIKE ? ESCAPE '\\')";
        $args[] = $like;
        $args[] = $like;
    }
    if ($role !== '') {
        $sql .= ' AND role = ?';
        $args[] = $role;
    }
    if ($status !== '') {
        $sql .= ' AND status = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY full_name COLLATE NOCASE, user_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

// UM-04: name and email only. role and status are deliberately not parameters.
function updateUserProfile($pdo, $userId, $fullName, $email)
{
    $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE user_id = ?');
    $stmt->execute(array($fullName, $email, $userId));
    return $stmt->rowCount() === 1;
}

// UM-05 and login rehash.
function updateUserPassword($pdo, $userId, $passwordHash)
{
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
    $stmt->execute(array($passwordHash, $userId));
    return $stmt->rowCount() === 1;
}

// UM-06. The WHERE clause refuses to turn the LAST ACTIVE administrator into a
// non-administrator, and it does so atomically inside the UPDATE, so two simultaneous
// requests cannot both pass a separate "count first" check.
// Returns true when one row changed, false when the guard (or a missing user) blocked it.
function updateUserRoleGuarded($pdo, $userId, $newRole)
{
    $stmt = $pdo->prepare(
        "UPDATE users SET role = ?
         WHERE user_id = ?
           AND NOT (role = 'Administrator' AND status = 'Active' AND ? <> 'Administrator'
                    AND (SELECT COUNT(*) FROM users WHERE role = 'Administrator' AND status = 'Active') <= 1)"
    );
    $stmt->execute(array($newRole, $userId, $newRole));
    return $stmt->rowCount() === 1;
}

// UM-07. Same atomic guard: the last active administrator cannot be deactivated.
function updateUserStatusGuarded($pdo, $userId, $newStatus)
{
    $stmt = $pdo->prepare(
        "UPDATE users SET status = ?
         WHERE user_id = ?
           AND NOT (role = 'Administrator' AND status = 'Active' AND ? <> 'Active'
                    AND (SELECT COUNT(*) FROM users WHERE role = 'Administrator' AND status = 'Active') <= 1)"
    );
    $stmt->execute(array($newStatus, $userId, $newStatus));
    return $stmt->rowCount() === 1;
}

// UM-08. Refuses to delete the last active administrator. The foreign key from
// students.user_id and tutoring_requests is a second line of defence: SQLite itself
// rejects a delete that would orphan related rows.
function deleteUserGuarded($pdo, $userId)
{
    $stmt = $pdo->prepare(
        "DELETE FROM users
         WHERE user_id = ?
           AND NOT (role = 'Administrator' AND status = 'Active'
                    AND (SELECT COUNT(*) FROM users WHERE role = 'Administrator' AND status = 'Active') <= 1)"
    );
    $stmt->execute(array($userId));
    return $stmt->rowCount() === 1;
}

// UM-08 warning screen and delete policy: tutoring requests reachable through students.user_id.
function countRequestsForUser($pdo, $userId)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM tutoring_requests r JOIN students s ON r.student_id = s.student_id WHERE s.user_id = ?'
    );
    $stmt->execute(array($userId));
    return (int)$stmt->fetchColumn();
}
