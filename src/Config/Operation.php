<?php

declare(strict_types=1);

namespace Fureev\Trees\Config;

enum Operation
{
    case MakeRoot;
    case PrependTo;
    case AppendTo;
    case InsertBefore;
    case InsertAfter;
    case DeleteAll;
    case RestoreSelfOnly;
}
