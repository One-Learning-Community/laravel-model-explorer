<?php

namespace Workbench\App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\User;

trait HasAuthor
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
