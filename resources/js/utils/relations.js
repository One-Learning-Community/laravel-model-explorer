import {
    ArrowUpLeft,
    Diamond,
    DiamondPlus,
    GitBranch,
    GitFork,
    Layers,
    Link,
    Link2,
    Share2,
    Shuffle,
} from 'lucide-vue-next'
import { shortName } from './model.js'

export const RELATION_COLORS = {
    HasOne:         'badge-info',
    HasMany:        'badge-primary',
    HasOneThrough:  'badge-info',
    HasManyThrough: 'badge-primary',
    BelongsTo:      'badge-secondary',
    BelongsToMany:  'badge-accent',
    MorphTo:        'badge-warning',
    MorphOne:       'badge-warning',
    MorphMany:      'badge-warning',
    MorphToMany:    'badge-error',
    MorphedByMany:  'badge-error',
}

export const RELATION_ICONS = {
    HasOne:         Link,
    HasMany:        GitBranch,
    HasOneThrough:  Link2,
    HasManyThrough: GitFork,
    BelongsTo:      ArrowUpLeft,
    BelongsToMany:  Share2,
    MorphTo:        Diamond,
    MorphOne:       DiamondPlus,
    MorphMany:      Layers,
    MorphToMany:    Shuffle,
    MorphedByMany:  Shuffle,
}

export function relationColor(fqcn) {
    return RELATION_COLORS[shortName(fqcn)] ?? 'badge-ghost'
}

export function relationIcon(fqcn) {
    return RELATION_ICONS[shortName(fqcn)] ?? null
}
