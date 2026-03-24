<template>
    <section v-if="traits.length" id="traits" class="mb-8 scroll-mt-16">
        <h2 class="font-semibold uppercase tracking-widest text-base-content/40 mb-3">Traits</h2>
        <div class="flex flex-wrap gap-2">
            <span
                v-for="trait in traits"
                :key="trait"
                class="badge badge-neutral font-mono"
                :title="trait"
            >{{ shortName(trait) }}</span>
        </div>
    </section>
</template>

<script setup>
import { shortName } from '../../utils/model.js'

defineProps({
    traits: { type: Array, required: true },
})
</script>
