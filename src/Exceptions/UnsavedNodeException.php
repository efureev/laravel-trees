<?php

declare(strict_types=1);

namespace Fureev\Trees\Exceptions;

use Illuminate\Database\Eloquent\Model;

class UnsavedNodeException extends Exception
{
    public function __construct(protected Model $node, string $message = 'Node does not save')
    {
        parent::__construct($message);
    }
}
