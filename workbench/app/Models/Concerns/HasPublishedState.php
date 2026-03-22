<?php

namespace Workbench\App\Models\Concerns;

trait HasPublishedState
{
    public function scopePublished($query): void
    {
        $query->where('is_published', true);
    }
}
