<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fixture for index-role detection: column `a` leads a single-column index;
 * columns `b`, `c`, `d` form one composite index (b leading, c/d non-leading).
 */
class IndexedRecord extends Model
{
    protected $fillable = ['a', 'b', 'c', 'd'];
}
