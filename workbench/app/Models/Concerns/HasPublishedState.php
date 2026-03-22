<?php

namespace Workbench\App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasPublishedState
{
    public function scopePublished($query): void
    {
        $query->where('is_published', true);
    }

    /** Whether this model has been published. */
    public function publishedLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->is_published ? 'Published' : 'Draft',
        );
    }
}
