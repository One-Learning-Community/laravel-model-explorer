<template>
    <div>
        <div class="page-header">
            <h1 class="page-title">Models</h1>
            <input
                v-model="search"
                class="search"
                type="text"
                placeholder="Filter models…"
                autofocus
            />
        </div>

        <p v-if="error" class="error">{{ error }}</p>

        <div v-else-if="loading" class="muted">Loading…</div>

        <template v-else>
            <p v-if="filtered.length === 0" class="muted">No models match "{{ search }}".</p>

            <div v-else class="model-grid">
                <RouterLink
                    v-for="model in filtered"
                    :key="model.class"
                    :to="`/models/${encodeModel(model.class)}`"
                    class="model-card"
                >
                    <span class="model-name">{{ model.short_name }}</span>
                    <span class="model-table">{{ model.table }}</span>
                </RouterLink>
            </div>

            <p class="count">{{ filtered.length }} of {{ models.length }} models</p>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const models = ref([])
const search = ref('')
const loading = ref(true)
const error = ref(null)

const filtered = computed(() => {
    const q = search.value.toLowerCase()
    if (!q) return models.value
    return models.value.filter(
        m => m.short_name.toLowerCase().includes(q) || m.class.toLowerCase().includes(q)
    )
})

function encodeModel(className) {
    return btoa(className).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')
}

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

<style scoped>
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    gap: 1rem;
}

.page-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #f1f5f9;
}

.search {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 6px;
    color: #e2e8f0;
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
    width: 240px;
    outline: none;
}

.search::placeholder {
    color: #64748b;
}

.search:focus {
    border-color: #3b82f6;
}

.model-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.model-card {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 1rem 1.125rem;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 8px;
    text-decoration: none;
    transition: border-color 0.15s, background 0.15s;
}

.model-card:hover {
    border-color: #3b82f6;
    background: #1e3a5f;
}

.model-name {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #f1f5f9;
}

.model-table {
    font-size: 0.75rem;
    color: #64748b;
    font-family: ui-monospace, monospace;
}

.count {
    font-size: 0.75rem;
    color: #475569;
    margin: 0;
}

.muted {
    color: #64748b;
    font-size: 0.875rem;
}

.error {
    color: #f87171;
    font-size: 0.875rem;
}
</style>
