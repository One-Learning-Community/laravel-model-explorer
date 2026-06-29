<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * A model that throws when instantiated. Used to verify that a single broken
 * model is skipped rather than breaking the whole model list.
 */
class BrokenModel extends Model
{
    protected $table = 'broken';

    public function __construct(array $attributes = [])
    {
        throw new RuntimeException('This model cannot be instantiated.');
    }
}
