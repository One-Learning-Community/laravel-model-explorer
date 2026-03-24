<template>
    <nav v-if="sections.length > 1" class="sticky top-0 z-10 -mx-8 px-8 bg-base-100 border-b border-base-300 mb-8 flex gap-1">
        <button
            v-for="section in sections"
            :key="section.id"
            @click="emit('navigate', section.id)"
            class="px-4 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors cursor-pointer"
            :class="activeSection === section.id
                ? 'border-primary text-primary'
                : 'border-transparent text-base-content/50 hover:text-base-content'"
        >{{ section.label }}</button>
    </nav>
</template>

<script setup>
const props = defineProps({
    sections: { type: Array, required: true },
    activeSection: { type: String, required: true },
})

const emit = defineEmits(['navigate'])
</script>
