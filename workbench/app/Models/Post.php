<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\Concerns\HasAuthor;
use Workbench\App\Models\Concerns\HasPublishedState;

class Post extends Model
{
    use HasAuthor, HasPublishedState;

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

    /**
     * Posts created within the given number of days.
     */
    public function scopeRecent(Builder $query, int $days = 30, bool $published = true): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * The user who authored this post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Intentionally no return type — exercises source-scanning discovery path.
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The author of this post as a Model instance — exercises model-return serialisation. */
    public function authorModel(): Attribute
    {
        return Attribute::make(
            get: fn () => User::find($this->user_id),
        );
    }

    /** All posts by the same author — exercises collection-return serialisation. */
    public function siblingPosts(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user_id
                ? static::where('user_id', $this->user_id)->where('id', '!=', $this->id)->get()
                : new Collection,
        );
    }

    // No return type and not a relation — must not appear in relations list.
    public function activate()
    {
        $this->is_published = true;
    }
}
