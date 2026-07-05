<?php

namespace Workbench\App\Enums;

/**
 * Backed string enum used to exercise enum-cast expansion on the `posts.status` column.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
