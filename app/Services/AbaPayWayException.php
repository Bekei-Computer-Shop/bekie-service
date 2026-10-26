<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class AbaPayWayException extends RuntimeException
{
    public function __construct(string $message, public readonly array $errors)
    {
        parent::__construct($message);
    }
}
