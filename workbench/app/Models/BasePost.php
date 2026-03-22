<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Concerns\HasOwner;

class BasePost extends Model
{
    use HasOwner;

    protected $table = 'posts';

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('is_published', false);
    }
}
