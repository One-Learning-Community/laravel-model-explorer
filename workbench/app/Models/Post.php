<?php

namespace Workbench\App\Models;

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

    protected $appends = ['summary'];

    public function getSummaryAttribute(): string
    {
        return '';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
