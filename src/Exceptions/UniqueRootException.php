<?php

namespace Fureev\Trees\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * Class UniqueRootException
 */
class UniqueRootException extends Exception
{
    protected $existRootModel;

    public function __construct(Model $existRootModel, $message = null)
    {
        $this->existRootModel = $existRootModel;
        if (!$message) {
            $message = 'Can not create more than one root. Exist: #' . $this->existRootModel->getKey();
        }
        parent::__construct($message);
    }
}
