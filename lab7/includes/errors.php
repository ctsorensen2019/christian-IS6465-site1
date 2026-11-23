<?php
// Exception types shared by the service layer and the pages.
// Each type maps to one HTTP outcome, so a page can catch them in a predictable order.

// Input failed server-side validation. Page shows the messages and returns HTTP 422.
// Extends InvalidArgumentException so Lab 6 style catch blocks still work.
class ValidationException extends InvalidArgumentException
{
    private $errors;

    public function __construct(array $errors)
    {
        parent::__construct(implode(' ', $errors));
        $this->errors = array_values($errors);
    }

    public function getErrors()
    {
        return $this->errors;
    }
}

// The actor is authenticated but not allowed to do this. HTTP 403.
class PermissionDeniedException extends RuntimeException
{
}

// The record does not exist (or the actor may not know it exists). HTTP 404.
class NotFoundException extends RuntimeException
{
}

// A business rule blocks the change (last administrator, related tutoring records,
// request no longer Open). HTTP 409.
class ConflictException extends RuntimeException
{
}
