<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = ['name'];

    /** Videos tagged with this tag — exercises many-to-many pivot detail. */
    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class)->withPivot('sort_order');
    }
}
