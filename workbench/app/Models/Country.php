<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Country extends Model
{
    protected $fillable = ['name'];

    /** Posts authored by this country's users — exercises has-many-through detail. */
    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(Post::class, User::class);
    }

    /**
     * A single post through this country's users — exercises has-ONE-through
     * detail. Regression guard: Laravel 11+ moved HasOneThrough off the
     * HasManyThrough hierarchy, so a stale instanceof left this blank.
     */
    public function firstPost(): HasOneThrough
    {
        return $this->hasOneThrough(Post::class, User::class);
    }
}
