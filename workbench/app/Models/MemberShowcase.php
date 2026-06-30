<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Concerns\HasOwner;

/**
 * Exercises the list-members surface (ADR-012 §C): a constant, a config
 * property, a lifecycle hook, a business method, an accessor, a private
 * helper, and a trait-provided relation (for provenance).
 */
class MemberShowcase extends Model
{
    use HasOwner;

    public const int MAX_TAGS = 5;

    protected $table = 'posts';

    protected $fillable = ['title'];

    protected static function booted(): void
    {
        //
    }

    /** Archive this record at the given time. */
    public function archive(\DateTimeInterface $at): void
    {
        //
    }

    public function shortLabel(): Attribute
    {
        return Attribute::make(get: fn () => 'x');
    }

    private function computeChecksum(): int
    {
        return 0;
    }
}
