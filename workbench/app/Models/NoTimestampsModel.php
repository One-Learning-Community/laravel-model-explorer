<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

class NoTimestampsModel extends Model
{
    public $timestamps = false;
}
