<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    protected $fillable = ['body'];

    /** The model this comment was left on — exercises MorphTo morph-type detail. */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
