<template>
    <form @submit.prevent="emit('submit')" class="flex gap-2 items-end mb-8 flex-wrap">
        <div class="form-control">
            <label class="label pb-1"><span class="label-text text-xs">Field</span></label>
            <select
                :value="selectedField"
                @change="emit('update:selectedField', $event.target.value)"
                class="select select-sm select-bordered font-mono"
            >
                <option v-for="f in availableFields" :key="f" :value="f">{{ f }}</option>
            </select>
        </div>
        <div class="form-control grow">
            <label class="label pb-1"><span class="label-text text-xs">Value</span></label>
            <input
                :value="lookupValue"
                @input="emit('update:lookupValue', $event.target.value)"
                type="text"
                placeholder="Enter value…"
                class="input input-sm input-bordered font-mono"
                required
            />
        </div>
        <button type="submit" class="btn btn-sm btn-primary" :disabled="loading">
            <span v-if="loading" class="loading loading-spinner loading-xs"></span>
            Find
        </button>
    </form>
</template>

<script setup>
defineProps({
    availableFields: { type: Array, required: true },
    selectedField: { type: String, default: '' },
    lookupValue: { type: String, default: '' },
    loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:selectedField', 'update:lookupValue', 'submit'])
</script>
