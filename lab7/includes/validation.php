<?php

function validatePreferredTime($preferredTime)
{
    $allowedTimes = ['Morning', 'Afternoon', 'Evening'];
    if (!in_array($preferredTime, $allowedTimes, true)) {
        return 'Choose a valid preferred time.';
    }
    return '';
}

function validateTopics($topics)
{
    $allowedTopics = ["Exam review", "Practice problems", "Study plan"];

    if ($topics === []) {
        return "Select at least one valid topic.";
    }

    foreach ($topics as $topic) {
        if (!in_array($topic, $allowedTopics, true)) {
            return "Select at least one valid topic.";
        }
    }

    return "";
}

function validateReason($reason)
{
    $length = mb_strlen(trim($reason));

    if ($length < 10 || $length > 500) {
        return "Describe your request using 10 to 500 characters.";
    }

    return "";
}


// ---------------------------------------------------------------------------
// Lab 7 user-management validators. Same convention as the Lab 4 validators above:
// return '' when the value is acceptable, otherwise the message to show the user.
// ---------------------------------------------------------------------------

const ROLE_STUDENT = 'Student';
const ROLE_TUTOR = 'Tutor';
const ROLE_ADMIN = 'Administrator';
const STATUS_ACTIVE = 'Active';
const STATUS_INACTIVE = 'Inactive';

// Roles a visitor may choose on the public registration form (approved scope).
function selfRegistrationRoles()
{
    return array(ROLE_STUDENT, ROLE_TUTOR);
}

// Every role that can be stored in users.role.
function allRoles()
{
    return array(ROLE_STUDENT, ROLE_TUTOR, ROLE_ADMIN);
}

function allStatuses()
{
    return array(STATUS_ACTIVE, STATUS_INACTIVE);
}

function validateFullName($name)
{
    $length = mb_strlen(trim($name));
    if ($length < 1 || $length > 120) {
        return 'Full name is required (up to 120 characters).';
    }
    return '';
}

function validateEmail($email)
{
    $email = trim($email);
    if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address.';
    }
    return '';
}

// OPEN DECISION in the design ("exact password requirements"). Default used here:
// 8 to 72 bytes (bcrypt ignores anything past 72 bytes) with at least one letter and one
// digit. Change the rule here and the message below, nowhere else.
function validatePassword($password)
{
    $bytes = strlen($password);
    if ($bytes < 8 || $bytes > 72 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must be 8 to 72 characters and include at least one letter and one number.';
    }
    return '';
}

function validatePasswordConfirmation($password, $confirmation)
{
    return hash_equals($password, $confirmation) ? '' : 'Password and confirmation do not match.';
}

function validateRole($role, $allowedRoles)
{
    return in_array($role, $allowedRoles, true) ? '' : 'Choose a valid account type.';
}

function validateStatus($status)
{
    return in_array($status, allStatuses(), true) ? '' : 'Choose a valid status.';
}
