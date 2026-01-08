<?php

declare(strict_types=1);

namespace Fureev\Trees\Exceptions;

class TreeNeedValueException extends Exception
{
    public function __construct(?string $message = null)
    {
        if (!$message) {
            $message = 'Model must contained {tree_id} ID';
        }
        parent::__construct($message);
    }
}
