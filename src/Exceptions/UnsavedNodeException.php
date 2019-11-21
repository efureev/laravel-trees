<?php

namespace Fureev\Trees\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * Class UnsavedNodeException
 */
class UnsavedNodeException extends Exception
{
    /** @var Model */
    protected $node;

    public function __construct(Model $node, string $message = 'Node does not save')
    {
        $this->node = $node;
        parent::__construct($message);
    }
}
