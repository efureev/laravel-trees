<?php

namespace Fureev\Trees\Exceptions;

/**
 * Class TreeNeedValueException
 */
class TreeNeedValueException extends Exception
{
    public function __construct($message = null)
    {
        if (!$message) {
            $message = 'Model must contained {tree_id}} ID';
        }
        parent::__construct($message);
    }
}
