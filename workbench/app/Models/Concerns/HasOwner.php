<?php

namespace Workbench\App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\User;

trait HasOwner
{
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
