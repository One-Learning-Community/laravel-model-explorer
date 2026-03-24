<template>
    <nav v-if="trail.length" class="flex items-center gap-1 text-xs mb-3 flex-wrap">
        <template v-for="(entry, i) in trail" :key="i">
            <RouterLink :to="linkFor(i)" class="link link-hover text-base-content/50 font-mono">
                {{ entry.display }}
            </RouterLink>
            <span class="text-base-content/30">›</span>
        </template>
        <span class="font-mono text-base-content/70">{{ currentDisplay }}</span>
    </nav>
</template>

<script setup>
import { encodeModel } from '../../utils/model.js'

const props = defineProps({
    trail: { type: Array, required: true },
    currentDisplay: { type: String, required: true },
})

function linkFor(index) {
    const entry = props.trail[index]
    const truncatedTrail = props.trail.slice(0, index)
    const params = new URLSearchParams({ field: entry.key_field, value: entry.key_value })
    if (truncatedTrail.length) {
        params.set('trail', JSON.stringify(truncatedTrail))
    }
    return `/models/${encodeModel(entry.model_class)}/record?${params}`
}
</script>
