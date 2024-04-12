<?php

declare(strict_types=1);

namespace Fureev\Trees\Exceptions;

use Illuminate\Database\Eloquent\Model;

class UniqueRootException extends Exception
{
    public function __construct(protected Model $existRootModel, string $message = null)
    {
        if (!$message) {
            $message = sprintf('Can not create more than one root. Exist: # %s', $this->existRootModel->getKey());
        }

        parent::__construct($message);
    }
}
