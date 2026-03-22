<template>
    <div>
        <RouterLink to="/" class="link link-hover text-sm text-base-content/50 mb-6 inline-block">
            ← All models
        </RouterLink>

        <div v-if="error" role="alert" class="alert alert-error text-sm">{{ error }}</div>
        <div v-else-if="loading" class="text-base-content/50 text-sm">Loading…</div>

        <template v-else-if="model">
            <div class="flex items-start justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl font-bold m-0 mb-1">{{ model.short_name }}</h1>
                    <span class="font-mono text-xs text-base-content/50">{{ model.class }}</span>
                </div>
                <div class="flex gap-2 items-center pt-1 shrink-0">
                    <span class="badge badge-ghost font-mono">{{ model.table }}</span>
                    <span class="badge" :class="model.uses_timestamps ? 'badge-success' : 'badge-ghost'">
                        {{ model.uses_timestamps ? 'timestamps' : 'no timestamps' }}
                    </span>
                </div>
            </div>

            <!-- Columns -->
            <section class="mb-8">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Columns</h2>
                <div class="overflow-x-auto" v-if="dbColumns.length">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Nullable</th>
                                <th>Attributes</th>
                                <th>Cast</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="attr in dbColumns" :key="attr.name">
                                <td>
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <span class="font-medium">{{ attr.name }}</span>
                                        <span v-if="attr.primary" class="badge badge-primary badge-xs">PK</span>
                                        <span v-if="attr.increments" class="badge badge-ghost badge-xs">auto</span>
                                    </div>
                                </td>
                                <td class="font-mono text-xs text-base-content/50">{{ attr.type }}</td>
                                <td>
                                    <span v-if="attr.nullable" class="badge badge-ghost badge-xs">null</span>
                                    <span v-else class="text-xs text-base-content/30">not null</span>
                                </td>
                                <td>
                                    <div class="flex gap-1 flex-wrap">
                                        <span v-if="attr.fillable" class="badge badge-success badge-xs">fillable</span>
                                        <span v-if="attr.hidden" class="badge badge-warning badge-xs">hidden</span>
                                        <span v-if="attr.unique" class="badge badge-ghost badge-xs">unique</span>
                                    </div>
                                </td>
                                <td class="font-mono text-xs text-base-content/50">{{ attr.cast ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-base-content/50">No columns found.</p>
            </section>

            <!-- Virtual attributes -->
            <section class="mb-8" v-if="virtualAttrs.length">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Virtual Attributes</h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Kind</th>
                                <th>Attributes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="attr in virtualAttrs" :key="attr.name">
                                <td class="font-medium">{{ attr.name }}</td>
                                <td class="font-mono text-xs text-base-content/50">{{ attr.cast }}</td>
                                <td>
                                    <div class="flex gap-1 flex-wrap">
                                        <span v-if="attr.appended" class="badge badge-primary badge-xs">appended</span>
                                        <span v-if="attr.fillable" class="badge badge-success badge-xs">fillable</span>
                                        <span v-if="attr.hidden" class="badge badge-warning badge-xs">hidden</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Traits -->
            <section class="mb-8" v-if="model.traits.length">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Traits</h2>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="trait in model.traits"
                        :key="trait"
                        class="badge badge-ghost font-mono"
                        :title="trait"
                    >{{ shortName(trait) }}</span>
                </div>
            </section>

            <!-- Scopes -->
            <section class="mb-8" v-if="model.scopes.length">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Scopes</h2>
                <template v-for="group in groupedScopes" :key="group.source ?? '__model__'">
                    <p v-if="group.label" class="text-xs text-base-content/40 mb-1 flex items-center gap-1">
                        via <span class="badge badge-ghost badge-xs font-mono" :title="group.source">{{ group.label }}</span>
                    </p>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <span v-for="scope in group.items" :key="scope.name" class="badge badge-ghost font-mono">
                            {{ scope.name }}
                        </span>
                    </div>
                </template>
            </section>

            <!-- Relations -->
            <section class="mb-8">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Relations</h2>
                <template v-if="groupedRelations.length">
                    <div v-for="group in groupedRelations" :key="group.source ?? '__model__'" class="mb-4">
                        <p v-if="group.label" class="text-xs text-base-content/40 mb-1 flex items-center gap-1">
                            via <span class="badge badge-ghost badge-xs font-mono" :title="group.source">{{ group.label }}</span>
                        </p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Method</th>
                                        <th>Type</th>
                                        <th>Related model</th>
                                        <th>Foreign key</th>
                                        <th>Local key</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="rel in group.items" :key="rel.name">
                                        <td class="font-mono text-sm">{{ rel.name }}</td>
                                        <td><span class="badge badge-primary badge-xs">{{ rel.type }}</span></td>
                                        <td>
                                            <RouterLink
                                                :to="`/models/${encodeModel(rel.related)}`"
                                                class="link link-primary font-medium"
                                            >{{ shortName(rel.related) }}</RouterLink>
                                            <span class="text-xs text-base-content/30 font-mono ml-2">{{ rel.related }}</span>
                                        </td>
                                        <td class="font-mono text-xs text-base-content/50">{{ rel.foreign_key ?? '—' }}</td>
                                        <td class="font-mono text-xs text-base-content/50">{{ rel.local_key ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
                <p v-else class="text-sm text-base-content/50">No relationships detected.</p>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const model = ref(null)
const loading = ref(true)
const error = ref(null)

const dbColumns = computed(() => model.value?.attributes.filter(a => !a.virtual) ?? [])
const virtualAttrs = computed(() => model.value?.attributes.filter(a => a.virtual) ?? [])

function groupBySource(items) {
    const groups = {}
    for (const item of items) {
        const key = item.defined_in ?? ''
        if (!groups[key]) groups[key] = []
        groups[key].push(item)
    }
    return Object.entries(groups).map(([key, members]) => ({
        source: key || null,
        label: key ? shortName(key) : null,
        items: members,
    }))
}

const groupedRelations = computed(() => groupBySource(model.value?.relations ?? []))
const groupedScopes = computed(() => groupBySource(model.value?.scopes ?? []))

function encodeModel(className) {
    return btoa(className).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')
}

function shortName(fqcn) {
    return fqcn.split('\\').pop()
}

async function load(slug) {
    loading.value = true
    error.value = null
    model.value = null
    try {
        const res = await fetch(`${window.modelExplorerBasePath}/api/models/${slug}`)
        if (res.status === 404) throw new Error('Model not found.')
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        model.value = await res.json()
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

onMounted(() => load(route.params.model))
watch(() => route.params.model, slug => { if (slug) load(slug) })
</script>
