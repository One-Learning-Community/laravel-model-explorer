<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Concerns\HasOwner;

class BasePost extends Model
{
    use HasOwner;

    protected $table = 'posts';
}
