<template>
    <div>
        <div class="flex items-center justify-between mb-6 gap-4">
            <h1 class="text-xl font-semibold m-0">Models</h1>
            <input
                v-model="search"
                type="text"
                placeholder="Filter models…"
                class="input input-bordered input-sm w-60"
                autofocus
            />
        </div>

        <div v-if="error" role="alert" class="alert alert-error text-sm mb-4">{{ error }}</div>
        <div v-else-if="loading" class="text-base-content/50 text-sm">Loading…</div>

        <template v-else>
            <p v-if="filtered.length === 0" class="text-base-content/50 text-sm">
                No models match "{{ search }}".
            </p>

            <div v-else class="grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-3 mb-4">
                <RouterLink
                    v-for="model in filtered"
                    :key="model.class"
                    :to="`/models/${encodeModel(model.class)}`"
                    class="card card-border bg-base-200 hover:border-primary transition-colors no-underline"
                >
                    <div class="card-body p-4 gap-1">
                        <span class="font-semibold text-base-content">{{ model.short_name }}</span>
                        <span class="font-mono text-xs text-base-content/50">{{ model.table }}</span>
                    </div>
                </RouterLink>
            </div>

            <p class="text-xs text-base-content/30 m-0">{{ filtered.length }} of {{ models.length }} models</p>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { encodeModel } from '../utils/model.js'

const models = ref([])
const search = ref('')
const loading = ref(true)
const error = ref(null)

const filtered = computed(() => {
    const q = search.value.toLowerCase()
    if (!q) return models.value
    return models.value.filter(
        m => m.short_name.toLowerCase().includes(q)
            || m.class.toLowerCase().includes(q)
            || m.table.toLowerCase().includes(q)
    )
})

onMounted(async () => {
    try {
        const res = await fetch(`${window.modelExplorerBasePath}/api/models`)
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        models.value = await res.json()
    } catch (e) {
        error.value = `Failed to load models: ${e.message}`
    } finally {
        loading.value = false
    }
})
</script>
