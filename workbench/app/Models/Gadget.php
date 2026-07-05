<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Vanilla HasFactory model — its factory is resolved by name convention, so
 * detection depends on the registered factory-name resolver.
 */
class Gadget extends Model
{
    use HasFactory;
}
