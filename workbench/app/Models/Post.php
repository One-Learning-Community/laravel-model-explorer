<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
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
}
