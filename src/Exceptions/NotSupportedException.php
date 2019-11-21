<?php

namespace Fureev\Trees\Exceptions;

/**
 * Class NotSupportedException
 */
class NotSupportedException extends Exception
{
    /**
     * MissingClassException constructor.
     *
     * @param string|null $className
     * @param string $message
     */
    public function __construct($className = null, $message = 'Not Supported')
    {
        $message .= $className ? (': ' . $className) : '';
        parent::__construct($message);
    }
}
