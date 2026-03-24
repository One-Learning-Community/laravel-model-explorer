<template>
    <div v-if="lastPage > 1" class="flex gap-1 items-center">
        <button
            class="btn btn-xs btn-ghost"
            :disabled="currentPage <= 1"
            @click="emit('paginate', currentPage - 1)"
        >‹</button>
        <span>{{ currentPage }} / {{ lastPage }}</span>
        <button
            class="btn btn-xs btn-ghost"
            :disabled="currentPage >= lastPage"
            @click="emit('paginate', currentPage + 1)"
        >›</button>
    </div>
</template>

<script setup>
const props = defineProps({
    currentPage: { type: Number, required: true },
    lastPage: { type: Number, required: true },
})

const emit = defineEmits(['paginate'])
</script>
