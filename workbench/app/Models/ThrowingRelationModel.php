<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reproduces a real-world relation that throws when the relation method is
 * invoked on a blank model instance. A whereHas closure runs at constraint-build
 * time; here it blows up (mirroring closures that dereference a property on the
 * query Builder). The RelationFinder must still report this relation — derived
 * from its declared return type and source — rather than silently dropping it.
 */
class ThrowingRelationModel extends Model
{
    protected $table = 'throwing_relation_models';

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function publishedPosts(): HasMany
    {
        return $this->hasMany(Post::class)
            ->whereHas('user', function ($query): void {
                throw new \RuntimeException('relation constraint blew up at build time');
            });
    }

    // Untyped (older-style) relation that also throws on invoke — the type must
    // be recovered from the relation primitive in the source body.
    public function archivedPosts()
    {
        return $this->hasMany(Post::class)
            ->whereHas('user', function ($query): void {
                throw new \RuntimeException('relation constraint blew up at build time');
            });
    }
}
