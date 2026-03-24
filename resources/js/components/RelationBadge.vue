<template>
    <span class="badge badge-xs gap-1" :class="color" :title="type">
        <component :is="icon" v-if="icon" :size="10" />
        {{ shortName(type) }}
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { shortName } from '../utils/model.js'
import { relationColor, relationIcon } from '../utils/relations.js'

const props = defineProps({
    type: { type: String, required: true },
})

const color = computed(() => relationColor(props.type))
const icon = computed(() => relationIcon(props.type))
</script>
