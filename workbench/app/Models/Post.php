<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\Concerns\HasAuthor;
use Workbench\App\Models\Concerns\HasPublishedState;

class Post extends Model
{
    use HasPublishedState, HasAuthor;

    protected $fillable = ['title', 'body', 'published_at'];

    protected $hidden = ['secret_key'];

    protected $casts = [
        'published_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    protected $appends = ['summary', 'excerpt'];

    public function getSummaryAttribute(): string
    {
        return '';
    }

    // New-style accessor for a virtual (non-column) attribute — exercises Attribute::make() discovery.
    public function excerpt(): Attribute
    {
        return Attribute::make(
            get: fn () => substr($this->body ?? '', 0, 100),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Intentionally no return type — exercises source-scanning discovery path.
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // No return type and not a relation — must not appear in relations list.
    public function activate()
    {
        $this->is_published = true;
    }
}
