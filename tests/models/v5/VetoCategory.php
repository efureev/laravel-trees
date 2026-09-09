<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vetoes its own `deleting` and `restoring` events on demand. Eloquent lets a listener abort
 * either by returning false, and the package checks for that — but nothing exercised the check.
 */
class VetoCategory extends AbstractModel
{
    use SoftDeletes;

    public static bool $veto = false;

    protected $fillable = ['title'];

    protected $table = 'veto_categories';

    protected static function booted(): void
    {
        static::deleting(static fn() => static::$veto ? false : null);
        static::restoring(static fn() => static::$veto ? false : null);
    }
}
