<?php

namespace App\Services\Tasks;

use RuntimeException;

/** A refused workflow action; the code is the HTTP status to answer with (403 not allowed, 422 invalid) */
class TaskWorkflowException extends RuntimeException
{
    public function __construct(string $message, private int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
