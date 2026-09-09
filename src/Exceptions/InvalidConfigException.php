<?php

declare(strict_types=1);

namespace Fureev\Trees\Exceptions;

/**
 * The model was handed to something that needs a tree configuration and has none.
 *
 * This used to be `Php\Support\Exceptions\InvalidConfigException`, from a package the tree
 * borrowed three small things from. Catching it by its short name still works; the import has
 * to change.
 */
class InvalidConfigException extends Exception
{
}
